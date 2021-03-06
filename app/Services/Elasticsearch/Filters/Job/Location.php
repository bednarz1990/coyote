<?php

namespace Coyote\Services\Elasticsearch\Filters\Job;

use Coyote\Services\Elasticsearch\DslInterface;
use Coyote\Services\Elasticsearch\Filter;
use Coyote\Services\Elasticsearch\QueryBuilderInterface;

class Location extends Filter implements DslInterface
{
    const DISTANCE = '40km';

    /**
     * @var array
     */
    protected $locations = [];

    /**
     * @param array $locations
     */
    public function __construct(array $locations = [])
    {
        $this->setLocations($locations);
    }

    /**
     * @param array $locations
     */
    public function setLocations(array $locations)
    {
        $this->locations = $locations;
    }

    /**
     * @param QueryBuilderInterface $queryBuilder
     * @return mixed
     */
    public function apply(QueryBuilderInterface $queryBuilder)
    {
        if (empty($this->locations)) {
            return (object) [];
        }

        $geodistance = [];

        foreach ($this->locations as $location) {
            if ($this->isValid($location)) {
                $geodistance[] = [
                    'geo_distance' => [
                        'distance' => self::DISTANCE,
                        'locations.coordinates' => $location
                    ]
                ];
            }
        }

        if (empty($geodistance)) {
            return (object) [];
        }

        return [
            'nested' => [
                'path' => 'locations',
                'query' => [
                    'bool' => [
                        'should' => $geodistance
                    ]
                ]
            ]
        ];
    }

    /**
     * @param array $location
     * @return bool
     */
    private function isValid($location)
    {
        return !empty($location['lat']) && !empty($location['lon']);
    }
}
