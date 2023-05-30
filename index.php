<?php

namespace texnixe\Similar;

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Files;
use Kirby\Cms\Pages;
use Kirby\Exception\Exception;

/**
 * Kirby 3 Similar Plugin
 *
 * @version   3.0.0
 * @author    Sonja Broda <hello@sonjabroda.com>
 * @copyright Sonja Broda <hello@sonjabroda.com>
 * @link      https://github.com/texnixe/kirby3-similar
 * @license   MIT
 */
load([
    'texnixe\\similar\\similar' => 'lib/Similar.php'
], __DIR__);

Kirby::plugin('texnixe/similar', [
    'options'     => [
        'cache'    => option('texnixe.similar.cache', true),
        'expires'  => (60 * 24 * 7), // minutes
        'defaults' => [
            'fields'         => 'tags',
            'threshold'      => 0.1,
            'delimiter'      => ',',
            'languageFilter' => false,
        ],
    ],
    'pageMethods' => [
        'similar' => function (array $options = []) {
            try {
                return (new Similar($this, new Pages(), $options))->getSimilar();
            } catch (Exception $e) {
                return new Files();
            }
        },
    ],
    'fileMethods' => [
        'similar' => function (array $options = []) {
            try {
                return (new Similar($this, new Files(), $options))->getSimilar();
            } catch (Exception $e) {
                return new Files();
            }
        },
    ],
    'hooks'       => require __DIR__ . '/config/hooks.php',
]);
