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
     * Escapes special characters in a string for use in match.
     *
     * @param string $value Value to be escaped.
     *
     * @return string
     */
    public function escape($value)
    {
        static $search = [
            "\\",
            "'",
            "*",
            "/",
            ")",
            "(",
            "$",
            "~",
            "!",
            "@",
            "^",
            "-",
            "|",
            "<",
            "\x0",
            "=",
        ];

        static $replace = [
            "\\\\",
            "\\'",
            "\\\\\\\\*",
            "\\\\/",
            "\\\\)",
            "\\\\(",
            "\\\\\$",
            "\\\\~",
            "\\\\!",
            "\\\\@",
            "\\\\^",
            "\\\\-",
            "\\\\|",
            "\\\\<",
            " ",
            " ",
        ];

        $value = str_replace($search, $replace, $value);

        $stat = count_chars($value, 1);

        if (
            isset($stat[ord('"')])
            && $stat[ord('"')] % 2 === 1
        ) {
            $value = str_replace('"', '\\\"', $value);
        }

        return $value;
    }

}
