<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CasAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const CAS_NAMESPACE = 'cas';
    private const ROLE_PREFIX = 'ROLE_';
    private const ROLES_ATTRIBUTE = 'roles';
    private const TICKET_QUERY_PARAMETER = 'ticket';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly UserProvider $userProvider,
        private readonly string $loginUrl,
        private readonly string $validateUrl,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->query->has(self::TICKET_QUERY_PARAMETER);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $ticket = $request->query->getString(self::TICKET_QUERY_PARAMETER);
        if ('' === $ticket) {
            throw new AuthenticationException('Missing CAS ticket.');
        }

        $payload = $this->fetchValidationPayload($request, $ticket);
        [$userIdentifier, $attributes] = $this->parseValidationPayload($payload);
        $roles = $this->extractRoles($attributes);

        return new SelfValidatingPassport(
            new UserBadge($userIdentifier, fn (string $identifier) => $this->userProvider->loadFromCas($identifier, $attributes, $roles))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if (!$request->query->has(self::TICKET_QUERY_PARAMETER)) {
            return null;
        }

        return new RedirectResponse($this->buildServiceUrl($request));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response(
            'CAS authentication failed: '.$exception->getMessage(),
            Response::HTTP_FORBIDDEN
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $serviceUrl = $this->buildServiceUrl($request);

        return new RedirectResponse($this->loginUrl.'?service='.urlencode($serviceUrl));
    }

    private function fetchValidationPayload(Request $request, string $ticket): string
    {
        $validationUrl = $this->buildValidationUrl($request, $ticket);

        try {
            return $this->client->request('GET', $validationUrl)->getContent();
        } catch (HttpClientExceptionInterface $exception) {
            throw new AuthenticationException('CAS validation failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    private function buildValidationUrl(Request $request, string $ticket): string
    {
        return sprintf(
            '%s?ticket=%s&service=%s',
            $this->validateUrl,
            urlencode($ticket),
            urlencode($this->buildServiceUrl($request))
        );
    }

    private function buildServiceUrl(Request $request): string
    {
        $query = $request->query->all();
        unset($query[self::TICKET_QUERY_PARAMETER]);
        $queryString = $query ? '?'.http_build_query($query) : '';

        return $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().$queryString;
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function parseValidationPayload(string $payload): array
    {
        $xml = simplexml_load_string($payload, \SimpleXMLElement::class, LIBXML_NONET, self::CAS_NAMESPACE, true);
        if (false === $xml) {
            throw new AuthenticationException('Invalid XML response from CAS.');
        }

        if (isset($xml->authenticationFailure)) {
            throw new AuthenticationException('CAS Authentication Failure: '.trim((string) $xml->authenticationFailure));
        }

        if (!isset($xml->authenticationSuccess->user)) {
            throw new AuthenticationException('CAS authentication failed: unexpected response.');
        }

        $userIdentifier = trim((string) $xml->authenticationSuccess->user);
        if ('' === $userIdentifier) {
            throw new AuthenticationException('CAS authentication failed: empty user identifier.');
        }

        return [$userIdentifier, $this->extractAttributes($xml)];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAttributes(\SimpleXMLElement $xml): array
    {
        if (!isset($xml->authenticationSuccess->attributes)) {
            return [];
        }

        $attributes = [];
        $authAttributes = $xml->authenticationSuccess->attributes;

        foreach ($authAttributes->children(self::CAS_NAMESPACE, true) as $attribute) {
            $this->appendAttribute($attributes, $attribute);
        }

        foreach ($authAttributes->children() as $attribute) {
            $this->appendAttribute($attributes, $attribute);
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function appendAttribute(array &$attributes, \SimpleXMLElement $attribute): void
    {
        $name = $attribute->getName();
        $value = (string) $attribute;

        if (!isset($attributes[$name])) {
            $attributes[$name] = $value;

            return;
        }

        if (!is_array($attributes[$name])) {
            $attributes[$name] = [$attributes[$name]];
        }

        $attributes[$name][] = $value;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return string[]
     */
    private function extractRoles(array $attributes): array
    {
        $roles = ['ROLE_USER'];

        foreach ((array) ($attributes[self::ROLES_ATTRIBUTE] ?? []) as $role) {
            if (!is_string($role) || '' === trim($role)) {
                continue;
            }

            $normalizedRole = strtoupper(trim($role));
            if (!str_starts_with($normalizedRole, self::ROLE_PREFIX)) {
                $normalizedRole = self::ROLE_PREFIX.$normalizedRole;
            }

            $roles[] = $normalizedRole;
        }

        return array_values(array_unique($roles));
    }
}
