<?php

namespace rias\scout\utilities;

use Algolia\AlgoliaSearch\Exceptions\NotFoundException;
use Craft;
use craft\base\Utility;
use Illuminate\Support\Arr;
use rias\scout\engines\Engine;
use rias\scout\Scout;

class ScoutUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('scout', 'Scout Indices');
    }

    public static function id(): string
    {
        return 'scout-indices';
    }

    public static function iconPath(): string
    {
        return Craft::getAlias('@app/icons/magnifying-glass.svg');
    }

    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();

        $engines = Scout::$plugin->getSettings()->getEngines();

        $stats = $engines->map(function(Engine $engine) {
            $stats = [
                'name' => $engine->scoutIndex->indexName,
                'replicaIndex' => $engine->scoutIndex->replicaIndex,
                'hasSettings' => $engine->scoutIndex->indexSettings ?? null,
            ];

            if (!$engine->scoutIndex->replicaIndex) {
                $engineCriteria = collect(Arr::wrap($engine->scoutIndex->criteria));

                $criteriaSites = $engineCriteria->map(function($criteria) {
                    return $criteria->siteId;
                })->flatten()->unique()->values()->toArray();

                if (count($criteriaSites) === 1 && $criteriaSites[0] === '*') {
                    $sites = 'all';
                } else {
                    $sites = collect($criteriaSites)->map(function($siteId) {
                        return Craft::$app->getSites()->getSiteById($siteId);
                    })->implode('name', ', ');
                }

                $totalElements = $engineCriteria->reduce(function($carry, $criteria) {
                    return $carry + $criteria->count();
                }, 0);

                $elementType = $engine->scoutIndex->enforceElementType ? $engine->scoutIndex->elementType : 'Mixed Element Types';

                try {
                    $totalRecords = $engine->getTotalRecords();
                    $indexEmpty = false;
                } catch (NotFoundException $e) {
                    $totalRecords = 0;
                    $indexEmpty = true;
                }

                $stats = array_merge($stats, [
                    'elementType' => $elementType,
                    'sites' => $sites,
                    'indexed' => $totalRecords,
                    'indexEmpty' => $indexEmpty,
                    'elements' => $totalElements,
                ]);
            }
            return $stats;
        });

        return $view->renderTemplate('scout/utility', [
            'stats' => $stats,
        ]);
    }
}
