<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 29.08.2016
 */
namespace skeeks\cms\importCsvShopContent;
use skeeks\cms\importCsvContent\ImportCsvContentHandler;

/**
 * Class CsvContentHandler
 *
 * @package skeeks\cms\importCsvContent
 */
class ImportCsvShopContentHandler extends ImportCsvContentHandler
{
    public function init()
    {
        parent::init();

        $this->name = \Yii::t('skeeks/importCsvShopContent', '[CSV] Import of products');
    }
}