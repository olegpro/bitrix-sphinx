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
use Olegpro\BitrixSphinx\DB\SphinxConnection;
use Olegpro\BitrixSphinx\DB\SphinxSqlHelper;
use Bitrix\Main\NotSupportedException;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Application;

/** @property Base $entity */

class SphinxQuery extends Query
{
    /**
     * @var bool
     */
    private $disableEscapeMatch = false;

    /**
     * @var null|bool
     */
    private $useConnectionMasterOnly = null;

    /**
     * @var bool
     */
    private $disableQuoteAliasSelect = false;

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
     * @param Base|Query|string $source
     * @throws Main\ArgumentException
     */
    public function __construct($source)
    {
        parent::__construct($source);

        $settingsBitrixSphinx = Configuration::getValue('olegpro_bitrix_sphinx');

        if (
            is_array($settingsBitrixSphinx)
            && isset($settingsBitrixSphinx['disable_quite_alias_select'])
            && is_bool($settingsBitrixSphinx['disable_quite_alias_select'])
        ) {
            $this->disableQuoteAliasSelect = $settingsBitrixSphinx['disable_quite_alias_select'];
        }
    }

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
     * Sets a list of fileds in GROUP BY clause
     *
     * @param mixed $group
     * @return SphinxQuery|Query
     */
    public function setGroup($group)
    {
        return parent::setGroup($group);
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
     * @return string
     * @throws Main\SystemException
     */
    protected function buildSelect()
    {
        $sql = array();

        /** @var QueryChain $chain */
        foreach ($this->select_chains as $chain) {
            $sql[] = $this->getSqlDefinitionSelect(
                $chain,
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
     * @param QueryChain $chain
     * @param bool $withAlias
     * @return mixed|string
     * @throws Main\SystemException
     */
    private function getSqlDefinitionSelect(QueryChain $chain, $withAlias = false)
    {
        $sqlDef = $chain->getLastElement()->getSqlDefinition();

        if ($withAlias) {
            $helper = $chain->getLastElement()->getValue()->getEntity()->getConnection()->getSqlHelper();
            $sqlDef .= ' AS ' . ($this->isDisableQuoteAliasSelect() ? $chain->getAlias() : $helper->quote($chain->getAlias()));
        }

        return $sqlDef;
    }

    /**
     * @return mixed|string
     */
    protected function buildWhere()
    {
        $sql = parent::buildWhere();

        /** @var SphinxConnection $connection */
        $connection = $this->entity->getConnection();

        /** @var SphinxSqlHelper $helper */
        $helper = $connection->getSqlHelper();

        if (!empty($this->match)) {
            $match = is_array($this->match) ? reset($this->match) : $this->match;

            $match = trim($match);

            if (!empty($match)) {

                $sql = sprintf(
                    (!empty($sql) ? "MATCH('%s')\nAND %s" : "MATCH('%s')"),
                    $this->isDisableEscapeMatch() ? $match : $helper->escape($match),
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

    protected function buildOrder()
    {
        $sql = [];

        foreach ($this->order_chains as $chain) {
            $sort = isset($this->order[$chain->getDefinition()])
                ? $this->order[$chain->getDefinition()]
                : $this->order[$chain->getAlias()];

            $connection = $this->entity->getConnection();

            $helper = $connection->getSqlHelper();

            $sqlDefinition = $helper->quote($chain->getAlias());

            $sql[] = $sqlDefinition . ' ' . $sort;
        }

        return join(', ', $sql);
    }

    /**
     * @throws NotSupportedException
     */
    protected function buildJoin()
    {
        throw new NotSupportedException('Sphinx does not support joins');
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

        $sqlOption = $this->buildOption();

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
            if ($this->isEnableConnectionMasterOnly()) {
                Application::getInstance()->getConnectionPool()->useMasterOnly(true);
            }

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

            if ($this->isEnableConnectionMasterOnly()) {
                Application::getInstance()->getConnectionPool()->useMasterOnly(false);
            }

            static::$last_query = $query;
        }

        if ($this->isFetchModificationRequired()) {
            $result->addFetchDataModifier([$this, 'fetchDataModificationCallback']);
        }

        return $result;
    }

    /**
     * Set disableEscapeMatch enable flag
     *
     * @return SphinxQuery|Query
     */
    public function disableEscapeMatch()
    {
        $this->disableEscapeMatch = true;

        return $this;
    }

    /**
     * Set disableEscapeMatch enable flag
     *
     * @return SphinxQuery|Query
     */
    public function enableEscapeMatch()
    {
        $this->disableEscapeMatch = false;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDisableEscapeMatch()
    {
        return $this->disableEscapeMatch;
    }


    /**
     * @return bool
     */
    public function isEnableConnectionMasterOnly()
    {
        $masterOnly = $this->useConnectionMasterOnly;

        if ($masterOnly === null) {
            $settingsBitrixSphinx = Configuration::getValue('olegpro_bitrix_sphinx');

            if (is_array($settingsBitrixSphinx) && isset($settingsBitrixSphinx['use_connection_master_only'])) {
                $masterOnly = $settingsBitrixSphinx['use_connection_master_only'];
            }
        }

        return ($masterOnly === true);
    }

    /**
     * @return SphinxQuery|Query
     */
    public function disableConnectionMasterOnly()
    {
        $this->useConnectionMasterOnly = false;

        return $this;
    }

    /**
     * @return SphinxQuery|Query
     */
    public function enableConnectionMasterOnly()
    {
        $this->useConnectionMasterOnly = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDisableQuoteAliasSelect()
    {
        return $this->disableQuoteAliasSelect;
    }

    /**
     * @return SphinxQuery|Query
     */
    public function disableQuoteAliasSelect()
    {
        $this->disableQuoteAliasSelect = true;

        return $this;
    }

    /**
     * @return SphinxQuery|Query
     */
    public function enableQuoteAliasSelect()
    {
        $this->disableQuoteAliasSelect = false;

        return $this;
    }

}
