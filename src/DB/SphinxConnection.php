<?php
/**
 * Created by olegpro.ru.
 * User: Oleg Maksimenko <oleg.39style@gmail.com>
 * Date: 28.05.2017
 */

namespace Olegpro\BitrixSphinx\DB;

use Bitrix\Main\DB\MysqliConnection;

class SphinxConnection extends MysqliConnection
{

    /**
     * @return SphinxSqlHelper
     */
    protected function createSqlHelper()
    {
        return new SphinxSqlHelper($this);
    }

}
