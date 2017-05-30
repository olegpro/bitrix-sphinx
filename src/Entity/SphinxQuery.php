<?php
/**
 * Created by olegpro.ru.
 * User: Oleg Maksimenko <oleg.39style@gmail.com>
 * Date: 28.05.2017
 */

namespace Olegpro\BitrixSphinx\Entity;

use Bitrix\Main\Entity\Base;
use Bitrix\Main\Entity\Query;
use Bitrix\Main;
use Bitrix\Main\Entity\QueryChain;

/** @property Base $entity */

class SphinxQuery extends Query
{
    /**
     * @var
     */
    protected $match;

    /**
     * @var array
     */
    protected $option = [];

    /**
     * For disable table alias
     *
     * @var string
     */
    protected $custom_base_table_alias = '';

    /**
     * Sets a list of fields for SELECT clause
     *
     * @param array $select
     * @return SphinxQuery|Query
     */
    public function setSelect(array $select)
    {
        return parent::setSelect($select);
    }

    /**
     * Sets a list of filters for WHERE clause
     *
     * @param array $filter
     * @return SphinxQuery|Query
     */
    public function setFilter(array $filter)
    {
        return parent::setFilter($filter);
    }

    /**
     * Sets a limit for LIMIT n clause
     *
     * @param int $limit
     * @return SphinxQuery|Query
     */
    public function setLimit($limit)
    {
        return parent::setLimit($limit);
    }

    /**
     * Sets an offset for LIMIT n, m clause

     * @param int $offset
     * @return SphinxQuery|Query
     */
    public function setOffset($offset)
    {
        return parent::setOffset($offset);
    }

    /**
     * @param null $count
     * @return SphinxQuery|Query|null
     */
    public function countTotal($count = null)
    {
        return parent::countTotal($count);
    }

    /**
     * Sets a list of fields for ORDER BY clause
     *
     * @param mixed $order
     * @return SphinxQuery|Query
     */
    public function setOrder($order)
    {
        return parent::setOrder($order);
    }

    /**
     * @param array|string $match
     * @return SphinxQuery
     * @throws Main\ArgumentException
     */
    public function setMatch($match)
    {
        if (!(is_array($match) || is_string($match))) {
            throw new Main\ArgumentException(sprintf(
                'Invalid match'
            ));
        }

        $this->match = $match;

        return $this;
    }

    /**
     * Sets a list of fields for OPTION clause
     *
     * @param array $option
     * @return SphinxQuery
     * @throws Main\ArgumentException
     */
    public function setOption(array $option)
    {
        if (!is_array($option)) {
            throw new Main\ArgumentException(sprintf(
                'Invalid option'
            ));
        }

        $this->option = $option;

        return $this;
    }

    /**
     * @return array|mixed|string
     */
    protected function buildSelect()
    {
        $sql = array();

        /** @var QueryChain $chain */
        foreach ($this->select_chains as $chain) {
            $sql[] = $chain->getSqlDefinition(
                ($chain->getLastElement()->getValue()->getColumnName() !== 'id')
            );
        }

        if (empty($sql)) {
            $sql[] = 1;
        }

        $sql = "\n\t" . join(",\n\t", $sql);

        return $sql;
    }

    /**
     * @return mixed|string
     */
    protected function buildWhere()
    {
        $sql = parent::buildWhere();

        $connection = $this->entity->getConnection();

        $helper = $connection->getSqlHelper();

        if (!empty($this->match)) {
            $match = is_array($this->match) ? reset($this->match) : $this->match;

            $match = trim($match);

            if (!empty($match)) {

                $sql = sprintf(
                    (!empty($sql) ? "MATCH('%s')\nAND %s" : "MATCH('%s')"),
                    $helper->forSql($match),
                    $sql
                );

            }

        }

        return $sql;
    }

    /**
     * @return string
     */
    protected function buildOption()
    {
        $connection = $this->entity->getConnection();

        $helper = $connection->getSqlHelper();

        $sql = array();

        foreach ($this->option as $key => $value) {
            $sql[] = sprintf('%s = %s', $helper->forSql($key), ($value));
        }

        return join(', ', $sql);
    }

    protected function buildQuery()
    {
        $connection = $this->entity->getConnection();
        $helper = $connection->getSqlHelper();

        if ($this->query_build_parts === null) {

            foreach ($this->select as $key => $value) {
                $this->addToSelectChain($value, is_numeric($key) ? null : $key);
            }

            $this->setFilterChains($this->filter);
            $this->divideFilter();

            foreach ($this->group as $value) {
                $this->addToGroupChain($value);
            }

            foreach ($this->order as $key => $value) {
                $this->addToOrderChain($key);
            }

            $sqlSelect = $this->buildSelect();
            $sqlWhere = $this->buildWhere();
            $sqlGroup = $this->buildGroup();
            $sqlHaving = $this->buildHaving();
            $sqlOrder = $this->buildOrder();
            $sqlOption = $this->buildOption();

            $sqlFrom = $this->quoteTableSource($this->entity->getDBTableName());

            $this->query_build_parts = array_filter(array(
                'SELECT' => $sqlSelect,
                'FROM' => $sqlFrom,
                'WHERE' => $sqlWhere,
                'GROUP BY' => $sqlGroup,
                'HAVING' => $sqlHaving,
                'ORDER BY' => $sqlOrder,
            ));
        }

        $build_parts = $this->query_build_parts;

        foreach ($build_parts as $k => &$v) {
            $v = $k . ' ' . $v;
        }

        $query = join("\n", $build_parts);

        list($query, $replaced) = $this->replaceSelectAliases($query);
        $this->replaced_aliases = $replaced;

        if ($this->limit > 0) {
            $query = $helper->getTopSql($query, $this->limit, $this->offset);
        }

        if (!empty($sqlOption)) {
            $query = sprintf("%s\nOPTION %s", trim($query), $sqlOption);
        }

        // Fix empty artefacts empty table alias
        $query = str_replace(sprintf('%s.', $helper->getLeftQuote() . $helper->getLeftQuote()), '', $query);

        return $query;
    }

    /**
     * @param $query
     * @return Main\DB\Result|null
     */
    protected function query($query)
    {
        $connection = $this->entity->getConnection();

        /** @var Main\DB\Result $result */
        $result = null;

        if ($result === null) {
            $result = $connection->query($query);
            $result->setReplacedAliases($this->replaced_aliases);

            if ($this->countTotal) {
                $cnt = null;

                foreach ($connection->query('SHOW META;')->fetchAll() as $metaRow) {
                    if (
                        isset($metaRow['Variable_name'], $metaRow['Value'])
                        && $metaRow['Variable_name'] === 'total'
                    ) {
                        $cnt = (int) $metaRow['Value'];

                        break;
                    }
                }

                $result->setCount($cnt);
            }

            static::$last_query = $query;
        }

        if ($this->isFetchModificationRequired()) {
            $result->addFetchDataModifier([$this, 'fetchDataModificationCallback']);
        }

        return $result;
    }

}
