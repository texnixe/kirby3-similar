<?php

namespace texnixe\Similar;


class Similar
{

    private static $indexname = null;

    private static $cache = null;

    private static function cache(): \Kirby\Cache\Cache
    {
        if (!static::$cache) {
            static::$cache = kirby()->cache('texnixe.similar');
        }
        // create new index table on new version of plugin
        if (!static::$indexname) {
            static::$indexname = 'index'.str_replace('.', '', kirby()->plugin('texnixe/similar')->version()[0]);
        }
        return static::$cache;
    }

    public static function flush()
    {
        if (static::$cache) {
            return static::cache()->flush();
        }
    }

    public static function data($basis, $options = [])
    {
        // new empty collection
        $similar = static::getClassName($basis, []);

        $defaults = option('texnixe.similar.defaults');
        // add the default search collection to defaults
        $defaults['index'] = $basis->siblings(false);

        // Merge default and user options
        $options = array_merge($defaults, $options);

        // define variables
        $index            = $options['index'];
        $fields           = $options['fields'];
        $threshold        = $options['threshold'];
        $delimiter        = $options['delimiter'];
        $languageFilter   = $options['languageFilter'];

        $searchItems = [];
         // get search items from active basis
        if(is_array($fields)) {
             //$searchField = null;
            foreach($fields as $field => $factor) {
                // only include fields that have values
                $values = $basis->{$field}()->split(',');
                if(count($values) > 0) {
                    $searchItems[$field][$field] = $values;
                    $searchItems[$field]['factor'] = $factor;
                }
            }

        }
        if(is_string($fields)) {
            $field = $fields;
            $searchItems[$field][$field]  = $basis->{$field}()->split($delimiter);
            $searchItems[$field]['factor'] = 1;
        }

         // stop and return an empty collection if the given field doesn't contain any values
         if(empty($searchItems)) {
            return $similar;
         }

        // calculate Jaccard index for each item, filter by given JI threshold and sort
        $similar = $index->map(function($item) use($searchItems, $delimiter) {

            $item->jaccardIndex = static::getSimilarityIndex($item, $searchItems, $delimiter);
            return $item;

        })->filterBy('jaccardIndex', '>=', $threshold)->sortBy('jaccardIndex', 'desc');


        // filter collection by current language if $languageFilter set to true
        if(kirby()->multilang() === true && $languageFilter === true) {
            $similar = $similar->filter(function($item) {
                return $item->translation(kirby()->language()->code())->exists();
            });
        }

        return $similar;
    }

    public static function getSimilarityIndex($item, $searchItems, $delimiter) {
        $indices = [];
        foreach($searchItems as $field => $value) {
            $comparisonArray = $item->{$field}()->split($delimiter);
            $intersection = count(array_intersect($value[$field], $comparisonArray));
            $union = count(array_unique(array_merge($value[$field], $comparisonArray)));
            $indices[] =  number_format($intersection/$union * $value['factor'], 5);
        }
        return array_sum($indices)/count($indices);

    }

    public static function getClassName($basis, $items = '')
    {
        if(is_a($basis, 'Kirby\Cms\Page')) {
            return pages($items);
        }
        if(is_a($basis, 'Kirby\Cms\File')) {
            return new \Kirby\Cms\Files($items);
        }
    }

    public static function getSimilar($basis, $options = [])
    {
        $collection = $options['index']?? $basis->siblings(false);
        if(option('texnixe.similar.cache') === true && $response = static::cache()->get(md5($basis->id() . json_encode($options)))) {
            // try to get data from the cache, else generate new collection
            $data = $response['data'];
            $similar = static::getClassName($basis, array_keys($data));

        } else {
            if(option('texnixe.similar.cache') === false) {
                static::cache()->flush();
            }
            $similar = static::data($basis, $options);
            static::cache()->set(
                md5($basis->id() . json_encode($options)),
                $similar,
                option('texnixe.similar.expires')
            );

        }

        return $similar;
    }
}
