<?php

namespace texnixe\Similar;

use JsonException;
use Kirby\Cache\Cache;
use Kirby\Cms\App as Kirby;
use Kirby\Cms\File;
use Kirby\Cms\Files;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\User;
use Kirby\Cms\Users;
use Kirby\Exception\DuplicateException;
use Kirby\Exception\Exception;
use Kirby\Exception\InvalidArgumentException;
use Kirby\Toolkit\A;

class Similar
{
    /**
     * Cache object
     *
     * @var Cache|null $cache
     */
    protected static ?Cache $cache = null;

    /**
     * Delimiter
     *
     * @var string
     */
    protected string $delimiter;

    /**
     * Single field as string or multiple fields as array
     *
     * @var string|array
     */
    protected string|array $fields;

    /**
     * Collection to search in
     *
     * @var Files|Pages|Users
     */
    protected Files|Pages|Users $index;

    /**
     * Filter results by language
     *
     * @var bool
     */
    protected bool $languageFilter;

    /**
     * Threshold for results to count
     *
     * @var float
     */
    protected float $threshold;

    /**
     * Base object
     *
     * @var File|Page|User $base File or page object.
     */
    protected File|Page|User $base;

    /**
     * Collection type
     *
     * @var Files|Pages|Users $collection
     */
    protected Files|Pages|Users $collection;

    /**
     * User options
     *
     * @var array
     */
    protected array $options;

    public function __construct(File|Page|User $base, Files|Pages|Users $collection, array $options)
    {

        $defaults              = option('texnixe.similar.defaults');
        $defaults['index']     = $base->siblings(false);
        $this->options         = array_merge($defaults, $options);
        $this->base            = $base;
        $this->collection      = $collection;
        $this->delimiter       = $this->options['delimiter'];
        $this->fields          = $this->options['fields'];
        $this->index           = $this->options['index'];
        $this->languageFilter  = $this->options['languageFilter'];
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
        } catch (InvalidArgumentException) {
            return false;
        }
    }


    /**
     * Returns the similarity index
     *
     * @param File|Page $item
     * @param array $searchItems
     *
     * @return float
     */
    protected function calculateSimilarityIndex(File|Page|User $item, array $searchItems): float
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

        return 0.0;
    }

    /**
     * Fetches similar pages
     *
     * @return Files|Pages|Users
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getData(): Files|Pages|Users
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
     * @throws Exception
     */
    protected function getSearchItems(): array
    {
        $searchItems  = [];
        $fields       = $this->fields;

        if (!is_string($fields) && !is_array($fields)) {
            throw new InvalidArgumentException('Fields must be provided as string or array');
        }

        if (is_string($fields)) {
            $field                         = $fields;
            $searchItems[$field][$field]   = $this->base->{$field}()->split($this->delimiter);
            $searchItems[$field]['factor'] = 1;

            return $searchItems;
        }

        if (A::isAssociative($fields)) {
            return $this->searchItemsForAssociativeArray($fields);
        }

        return $this->searchItemsForIndexArray($fields);

    }

    /**
     * Returns similar pages
     *
     * @return Files|Pages|Users
     * @throws DuplicateException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getSimilar(): Files|Pages|Users
    {
        // try to get data from the cache, else create new
        if (option('texnixe.similar.cache') === true && $response = static::cache()->get(md5($this->version() . $this->base->id() . json_encode($this->options, JSON_THROW_ON_ERROR)))) {
            foreach ($response as $key => $data) {
                $this->collection->add($key);
            }
            return $this->collection;
        }

        // else fetch new data and store in cache
        // make sure we store no old stuff in the cache
        if (option('texnixe.similar.cache') === false) {
            static::cache()->flush();
        }
        $this->collection = $this->getData();
        static::cache()->set(
            md5($this->version() . $this->base->id() . json_encode($this->options, JSON_THROW_ON_ERROR)),
            $this->collection->toArray(),
            option('texnixe.similar.expires')
        );

        return $this->collection;

    }

    /**
     * Filters items by Jaccard Index
     *
     * @param array $searchItems
     *
     * @return Files|Pages|Users
     */
    protected function filterByJaccardIndex(array $searchItems): Files|Pages|Users
    {
        return $this->index
        ->filter(fn ($item) => $this->calculateSimilarityIndex($item, $searchItems) >= $this->threshold)
        ->sortBy(fn ($item) => $this->calculateSimilarityIndex($item, $searchItems),'desc');
    }

    /**
     *  Filters collection by current language if $languageFilter set to true
     *
     * @param $similar
     *
     * @return Files|Pages|Users
     */
    protected function filterByLanguage($similar): Files|Pages|Users
    {
        if (kirby()->multilang() === true && ($language = kirby()->language())) {
            $similar = $similar->filter(fn ($item) => $item->translation($language->code())->exists());
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

    /**
     * Return seach items for associative array
     * @param array $fields
     * @return array
     * @throws InvalidArgumentException
     */
    private function searchItemsForAssociativeArray(array $fields): array
    {
        $searchItems = [];

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

        return $searchItems;
    }

    /**
     * Return search items for an indexed array
     *
     * @param array $fields
     * @return array
     */
    private function searchItemsForIndexArray(array $fields): array
    {
        $searchItems = [];

        foreach ($fields as $field) {
            // only include fields that have values
            $values = $this->base->{$field}()->split($this->delimiter);
            if (count($values) > 0) {
                $searchItems[$field][$field]    = $values;
                $searchItems[$field]['factor']  = 1;
            }
        }

        return $searchItems;

    }
}
