<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Router;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the current entity ID from the request, supporting both the canonical
 * "entityId" route parameter and its "id" alias.
 *
 * It also looks into "_route_params" because, for mapped route placeholders
 * (e.g. {entityId:user.id} or {id:user.id}), Symfony's value resolver may consume
 * the top-level request attribute while the original matched value remains stored
 * in "_route_params". The "id" alias is never read from the query string on purpose
 * ("id" is too common a query parameter); the route-matched value always lands in
 * the request attributes (or "_route_params").
 *
 * @internal
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class EntityIdReader
{
    public static function fromRequest(Request $request): mixed
    {
        /** @var array<string, mixed> $routeParams */
        $routeParams = $request->attributes->get('_route_params', []);

        return $request->attributes->get(EA::ENTITY_ID)
            ?? $request->attributes->get('id')
            ?? ($routeParams[EA::ENTITY_ID] ?? null)
            ?? ($routeParams['id'] ?? null)
            ?? $request->query->get(EA::ENTITY_ID);
    }
}
