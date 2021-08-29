<?php

namespace texnixe\Similar;

use JsonException;
use Kirby\Cache\Cache;
use Kirby\Cms\App as Kirby;
use Kirby\Cms\File;
use Kirby\Cms\Files;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Exception\DuplicateException;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;

class Similar
{
    /**
     * Cache object
     *
     * @var Cache $cache
     */
    protected static $cache;

    /**
     * Delimiter
     *
     * @var string
     */
    protected $delimiter;

    /**
     * Single field as string or multiple fields as array
     *
     * @var mixed
     */
    protected $fields;

    /**
     * Collection to search in
     *
     * @var Files|Pages
     */
    protected $index;

    /**
     * Filter results by language
     *
     * @var bool
     */
    protected $languageFilter;

    /**
     * Threshold for results to count
     *
     * @var float
     */
    protected $threshold;

    /**
     * Base object
     *
     * @var File|Page $base File or page object.
     */
    protected $base;

    /**
     * Collection type
     *
     * @var Files|Pages $collection
     */
    protected $collection;

    /**
     * User options
     *
     * @var array
     */
    protected $options;

    public function __construct($base, $collection, array $options)
    {
        $defaults              = option('texnixe.similar.defaults');
        $defaults['index']     = $base->siblings(false);
        $this->options         = array_merge($defaults, $options);
        $this->base            = $base;
        $this->collection      = $collection;
        $this->delimiter       = $this->options['delimiter'];
        $this->fields          = $this->options['fields'];
        $this->index           = $this->options['index'];
        $this->languageFilter  = $this->options['delimiter'];
        $this->threshold       = $this->options['threshold'];
    }

    /**
     * Returns the cache object
     *
     * @return Cache
     * @throws InvalidArgumentException
     */
    protected static function cache(): Cache
    {
        if (!static::$cache) {
            static::$cache = kirby()->cache('texnixe.similar');
        }

        return static::$cache;
    }

    /**
     * Flushes the cache
     *
     * @return bool
     */
    public static function flush(): bool
    {
        try {
            return static::cache()->flush();
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }


    /**
     * Returns the similarity index
     *
     * @param mixed $item
     * @param array $searchItems
     *
     * @return float
     */
    protected function calculateSimilarityIndex($item, array $searchItems): float
    {
        $indices = [];
        foreach ($searchItems as $field => $value) {
            $itemFieldValues = $item->{$field}()->split($this->delimiter);
            $intersection    = count(array_intersect($value[$field], $itemFieldValues));
            $union           = count(array_unique(array_merge($value[$field], $itemFieldValues)));
            if ($union !== 0) {
                $indices[] = number_format($intersection / $union * $value['factor'], 5);
            }
        }
        if (($indexCount = count($indices)) !== 0) {
            return array_sum($indices) / $indexCount;
        }

        return (float)0;
    }

    /**
     * Fetches similar pages
     *
     * @return Files|Pages
     * @throws InvalidArgumentException
     */
    public function getData()
    {
        // initialize new collection based on type
        $similar  = $this->collection;

        // Merge default and user options
        $searchItems = $this->getSearchItems();

        // stop and return an empty collection if the given field doesn't contain any values
        if (empty($searchItems)) {
            return $similar;
        }

        // calculate Jaccard index for each item, filter by given JI threshold and sort
        $similar = $this->filterByJaccardIndex($searchItems);


        // filter collection by current language if $languageFilter set to true
        if ($this->languageFilter === true) {
            $similar = $this->filterByLanguage($similar);
        }

        return $similar;
    }

    /**
     * Returns collection to search in
     *
     * @return array
     * @throws InvalidArgumentException
     */
    protected function getSearchItems(): array
    {
        $searchItems  = [];
        $fields       = $this->fields;
        if (is_array($fields)) {
            if (A::isAssociative($fields)) {
                foreach ($fields as $field => $factor) {
                    if (is_string($field) === false) {
                        throw new InvalidArgumentException('Field array must be simple array or associative array');
                    }
                    // only include fields that have values
                    $values = $this->base->{$field}()->split($this->delimiter);
                    if (count($values) > 0) {
                        $searchItems[$field][$field]    = $values;
                        $searchItems[$field]['factor']  = $factor;
                    }
                }
            } else {
                foreach ($fields as $field) {
                    // only include fields that have values
                    $values = $this->base->{$field}()->split($this->delimiter);
                    if (count($values) > 0) {
                        $searchItems[$field][$field]    = $values;
                        $searchItems[$field]['factor']  = 1;
                    }
                }
            }
        }
        if (is_string($fields)) {
            $field                         = $fields;
            $searchItems[$field][$field]   = $this->base->{$field}()->split($this->delimiter);
            $searchItems[$field]['factor'] = 1;
        }
        return $searchItems;
    }

    /**
     * Returns similar pages
     *
     * @return Files|Pages
     * @throws DuplicateException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getSimilar()
    {
        // try to get data from the cache, else create new
        if (option('texnixe.similar.cache') === true && $response = static::cache()->get(md5($this->version() . $this->base->id() . json_encode($this->options, JSON_THROW_ON_ERROR)))) {
            foreach ($response as $key => $data) {
                $this->collection->add($key);
            }
            $similar = $this->collection;
        // else fetch new data and store in cache
        } else {
            // make sure we store no old stuff in the cache
            if (option('texnixe.similar.cache') === false) {
                static::cache()->flush();
            }
            $similar = $this->getData();
            static::cache()->set(
                md5($this->version() . $this->base->id() . json_encode($this->options, JSON_THROW_ON_ERROR)),
                $similar->toArray(),
                option('texnixe.similar.expires')
            );
        }

        return $similar;
    }


    /**
     * Filters items by Jaccard Index
     *
     * @param array $searchItems
     *
     * @return Files|Pages|\Kirby\Toolkit\Collection
     */
    protected function filterByJaccardIndex(array $searchItems)
    {
        return $this->index->map(function ($item) use ($searchItems) {
            $item->jaccardIndex = $this->calculateSimilarityIndex($item, $searchItems);
            return $item;
        })->filterBy('jaccardIndex', '>=', $this->threshold)->sortBy('jaccardIndex', 'desc');
    }

    /**
     *  Filters collection by current language if $languageFilter set to true
     *
     * @param $similar
     *
     * @return Files|Pages
     */
    protected function filterByLanguage($similar)
    {
        if (kirby()->multilang() === true && ($language = kirby()->language())) {
            $similar = $similar->filter(function ($item) use ($language) {
                return $item->translation($language->code())->exists();
            });
        }
        return $similar;
    }

    /**
     * Returns plugin version
     *
     * @throws DuplicateException
     */
    public function version()
    {
        return Kirby::plugin('texnixe/similar')->version()[0];
    }
}
