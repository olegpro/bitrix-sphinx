# Olegpro\\BitrixSphinx

[![Build Status](https://travis-ci.org/olegpro/bitrix-sphinx.svg)](https://travis-ci.org/olegpro/bitrix-sphinx)
[![Latest Stable Version](https://poser.pugx.org/olegpro/bitrix-sphinx/v/stable)](https://packagist.org/packages/olegpro/bitrix-sphinx) 
[![Total Downloads](https://poser.pugx.org/olegpro/bitrix-sphinx/downloads)](https://packagist.org/packages/olegpro/bitrix-sphinx) 
[![License](https://poser.pugx.org/olegpro/bitrix-sphinx/license)](https://packagist.org/packages/olegpro/bitrix-sphinx)

Пакет добавляет в 1С-Битрикс возможность работать с индексами sphinx, через ORM D7, как с привычными сущностями (например \Bitrix\Iblock\ElementTable или \Bitrix\Catalog\PriceTable)

# Настройка

Создаём класс ORM-сущность:

```php
<?php

use Bitrix\Main;
use Bitrix\Main\Localization\Loc;
use Olegpro\BitrixSphinx\Entity\SphinxDataManager;
use Olegpro\BitrixSphinx\Entity\SphinxQuery;

Loc::loadMessages(__FILE__);

class SampleTable extends SphinxDataManager
{

    /**
     * Returns index sphinx name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'sample_index';
    }

    /**
     * Returns sphinx-connection name for entity
     *
     * @return string
     */
    public static function getConnectionName()
    {
        return 'sphinx';
    }

    /**
     * Creates and returns the Query object for the entity
     *
     * @return SphinxQuery
     */
    public static function query()
    {
        return new SphinxQuery(static::getEntity());
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            new Main\Entity\IntegerField('id', [
                'primary' => true,
            ]),
            new Main\Entity\StringField('name'),
            new Main\Entity\BooleanField('available', [
                'values' => [0, 1],
            ])
        ];

    }

}

```

Описываем новое подключение в файле /bitrix/.settings.php в секцию connections:

```php
'connections' =>
    array(
        'value' =>
            array(
                'default' =>
                        array(
                            // ...
                        )
                    ),
                'sphinx' =>
                    array(
                        'className' => '\\Olegpro\\BitrixSphinx\\DB\\SphinxConnection',
                        'host' => '127.0.0.1:9306',
                        'database' => '',
                        'login' => '',
                        'password' => '',
                        'options' => 1,
                    ),
            ),
        'readonly' => true,
    ),
```

# Использование

```php
<?php

use Bitrix\Main\Application;
use Bitrix\Main\Entity\ExpressionFieldd;

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

Application::getConnection(SampleTable::getConnectionName())->startTracker(true);

$iterator = SampleTable::getList([
    'select' => [
        '*',
        new ExpressionField('weight', 'WEIGHT()', 'id'),
    ],
    'match' => 'книга',
    'filter' => [
        '=available' => 1,
    ],
    'limit' => 10,
    'order' => [
        'weight' => 'DESC',
    ],
    'option' => [
        'max_matches' => 50000,
    ],
]);

echo '<pre>';print_r($iterator->getTrackerQuery()->getSql());echo '</pre>';

echo '<pre>';print_r($iterator->fetchAll());echo '</pre>';

```

## С постраничной навигацией 

```php
<?php

use Bitrix\Main\Application;
use Bitrix\Main\Entity\ExpressionFieldd;
use Bitrix\Main\UI\PageNavigation;

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

Application::getConnection(SampleTable::getConnectionName())->startTracker(true);

$nav = new PageNavigation('s');

$nav->allowAllRecords(false)
    ->setPageSize(10)
    ->initFromUri();

$iterator = SampleTable::getList([
    'select' => [
        '*',
        new ExpressionField('weight', 'WEIGHT()', 'id'),
    ],
    'match' => 'книга',
    'filter' => [
        '=available' => 1,
    ],
    'count_total' => true,
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
    'order' => [
        'weight' => 'DESC',
    ],
    'option' => [
        'max_matches' => 50000,
    ],
]);

$nav->setRecordCount($iterator->getCount());

echo '<pre>';print_r($iterator->getTrackerQuery()->getSql());echo '</pre>';

echo '<pre>';print_r($iterator->fetchAll());echo '</pre>';


$APPLICATION->IncludeComponent(
    "bitrix:main.pagenavigation",
    "",
    array(
        "NAV_OBJECT" => $nav,
        "SEF_MODE" => "N",
    ),
    false
);

```


# Установка пакета

Добавить библиотеку в Composer:

```
composer require olegpro/bitrix-sphinx
```