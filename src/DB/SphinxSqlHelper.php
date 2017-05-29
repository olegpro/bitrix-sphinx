<?php
/**
 * Created by olegpro.ru.
 * User: Oleg Maksimenko <oleg.39style@gmail.com>
 * Date: 28.05.2017
 */

namespace Olegpro\BitrixSphinx\DB;

use Bitrix\Main\DB\MysqliSqlHelper;
use Bitrix\Main\DB\SqlHelper;

class SphinxSqlHelper extends MysqliSqlHelper
{

    /**
     * Returns quoted identifier.
     * <p>
     * For example Title become :
     * - `Title` for MySQL
     * <p>
     * @param string $identifier Column name.
     *
     * @return string
     * @see SqlHelper::getLeftQuote
     * @see SqlHelper::getRightQuote
     */
    public function quote0($identifier)
    {
        if ($identifier === '') {
            // security unshielding
            $identifier = str_replace(array($this->getLeftQuote(), $this->getRightQuote()), '', $identifier);

            return $identifier;
        } else {
            return parent::quote($identifier);
        }
    }

}
