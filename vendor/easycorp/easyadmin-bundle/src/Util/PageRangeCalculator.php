<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Util;

/**
 * Calculates the range of pages to display around the current page in a paginator.
 *
 * @internal
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class PageRangeCalculator
{
    /**
     * It returns the closest available pages around the current page.
     * E.g. a paginator with 35 pages, if current page = 1, returns [1, 2, 3, 4, null, 35]
     *      if current page = 18, returns [1, null, 15, 16, 17, 18, 19, 20, 21, null, 35]
     * NULL values mean a gap in the pagination (they can be represented as ellipsis in the templates).
     *
     * @return list<int|null>
     */
    public static function calculate(int $currentPage, int $lastPage, int $pagesOnEachSide = 3, int $pagesOnEdges = 1): array
    {
        if (0 === $pagesOnEachSide) {
            return [];
        }

        // when there are few enough pages, render all of them without gaps
        if ($lastPage <= ($pagesOnEachSide + $pagesOnEdges) * 2) {
            return range(1, $lastPage);
        }

        // leading section: the first edge page(s) + a gap when the current page is far
        // from the start, otherwise every page from the first up to the current one
        if ($currentPage > $pagesOnEachSide + $pagesOnEdges + 1) {
            $pages = [
                ...range(1, $pagesOnEdges),
                null,
                ...range($currentPage - $pagesOnEachSide, $currentPage),
            ];
        } else {
            $pages = range(1, $currentPage);
        }

        // trailing section: the pages after the current one + a gap and the last edge
        // page(s) when far from the end, otherwise every remaining page to the last
        if ($currentPage < $lastPage - $pagesOnEachSide - $pagesOnEdges - 1) {
            $pages = [
                ...$pages,
                ...range($currentPage + 1, $currentPage + $pagesOnEachSide),
                null,
                ...range($lastPage - $pagesOnEdges + 1, $lastPage),
            ];
        } elseif ($currentPage + 1 <= $lastPage) {
            $pages = [...$pages, ...range($currentPage + 1, $lastPage)];
        }

        return $pages;
    }
}
