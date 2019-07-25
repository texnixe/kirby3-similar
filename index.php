<?php

namespace texnixe\Similar;
/**
 * Kirby 3 Similar Plugin
 *
 * @version   1.0.1
 * @author    Sonja Broda <hello@sonjabroda.com>
 * @copyright Sonja Broda <hello@sonjabroda.com>
 * @link      https://github.com/texnixe/kirby3-similar
 * @license   MIT
 */

load([
    'texnixe\\similar\\similar' => 'src/Similar.php'
], __DIR__);

\Kirby::plugin('texnixe/similar', [
    'options' => [
        'cache' => true,
        'expires' => (60*24*7), // minutes
        'defaults' => [
            'fields'          => 'tags',
            'threshold'       => 0.1,
            'delimiter'       => ',',
            'languageFilter'  => false,
        ]
    ],
    'pageMethods' => [
        'similar' => function (array $options = []) {
            return Similar::getSimilar($this, $options);
        }
    ],
    'fileMethods' => [
        'similar' => function (array $options = []) {
            return Similar::getSimilar($this, $options);
        }
    ],
    'hooks'       => require 'config/hooks.php'
]);



