<?php declare(strict_types=1);

namespace Lmc\Cqrs\Bundle\Controller;

use Lmc\Cqrs\Types\QueryFetcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CacheController
{
    /**
     * Returns a list of deleted keys or status of deleted key if only one key was provided
     *
     * @phpstan-param QueryFetcherInterface<mixed, mixed> $queryFetcher
     */
    #[Route('/_profiler/cqrs-bundle/query/cache/invalidate', name: '_cqrs_query_cache_invalidate')]
    public function invalidateQueryCacheAction(
        QueryFetcherInterface $queryFetcher,
        Request $request,
    ): JsonResponse {
        /** @var string|array|null $keys */
        $keys = $request->query->get('key');
        $response = new JsonResponse();

        $result = [];

        if (!$keys) {
            return $response->setData(['status' => 'empty key']);
        }

        if (!is_array($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            $key = urldecode($key);
            $isInvalidated = $queryFetcher->invalidateCacheItem($key);

            $result[] = [
                'key' => $key,
                'isInvalidated' => $isInvalidated,
            ];
        }

        return $response->setData($result);
    }
}
