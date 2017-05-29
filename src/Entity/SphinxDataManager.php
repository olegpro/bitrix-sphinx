<?php
/**
 * Created by olegpro.ru.
 * User: Oleg Maksimenko <oleg.39style@gmail.com>
 * Date: 28.05.2017
 */

namespace Olegpro\BitrixSphinx\Entity;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\Result;

class SphinxDataManager extends DataManager
{

    /**
     * Executes the query and returns selection by parameters of the query.
     * This function is an alias to the Query object functions
     *
     * @param array $parameters An array of query parameters
     * @return Result
     * @throws ArgumentException
     */
    public static function getList(array $parameters = [])
    {
        /** @var SphinxQuery $query */
        $query = static::query();

        if (!isset($parameters['select'])) {
            $query->setSelect(['*']);
        }

        foreach ($parameters as $param => $value) {
            switch ($param) {
                case 'select':
                    $query->setSelect($value);
                    break;
                case 'filter':
                    $query->setFilter($value);
                    break;
                case 'group':
                    $query->setGroup($value);
                    break;
                case 'order';
                    $query->setOrder($value);
                    break;
                case 'limit':
                    $query->setLimit($value);
                    break;
                case 'offset':
                    $query->setOffset($value);
                    break;
                case 'count_total':
                    $query->countTotal($value);
                    break;
                case 'runtime':
                    foreach ($value as $name => $fieldInfo) {
                        $query->registerRuntimeField($name, $fieldInfo);
                    }
                    break;
                case 'data_doubling':
                    if ($value) {
                        $query->enableDataDoubling();
                    } else {
                        $query->disableDataDoubling();
                    }
                    break;
                case 'cache':
                    $query->setCacheTtl($value['ttl']);
                    if (isset($value['cache_joins'])) {
                        $query->cacheJoins($value['cache_joins']);
                    }
                    break;
                case 'match':
                    $query->setMatch($value);
                    break;
                case 'option':
                    $query->setOption($value);
                    break;
                default:
                    throw new ArgumentException(sprintf('Unknown parameter: %s', $param), $param);
            }
        }

        return $query->exec();
    }

}
