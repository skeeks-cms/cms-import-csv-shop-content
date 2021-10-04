<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\importCsvShopContent;

use skeeks\cms\importCsv\helpers\CsvImportRowResult;
use skeeks\cms\importCsv\ImportCsvHandler;
use skeeks\cms\importCsvContent\ImportCsvContentHandler;
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsContentProperty;
use skeeks\cms\shop\models\ShopCmsContentElement;
use skeeks\cms\shop\models\ShopProduct;
use skeeks\cms\shop\models\ShopProductPrice;
use skeeks\cms\shop\models\ShopStore;
use skeeks\cms\shop\models\ShopStoreProduct;
use yii\base\Exception;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\widgets\ActiveForm;

/**
 * @property ShopStore    $shopStore
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class ImportCsvShopStoreProductHandler extends ImportCsvHandler
{

    public $shop_store_id = null;
    public $titles_row_number = false;

    /**
     * @var bool Сохранять исодные данные по товару?
     */
    public $is_save_source_data = false;

    /**
     * @var bool Обнулить количество у файлов которых нет в файле?
     */
    public $is_quantity_clean = false;

    /**
     * Соответствие полей
     * @var array
     */
    public $matching = [];

    public function init()
    {
        parent::init();
        $this->name = \Yii::t('skeeks/importCsvShopContent', '[CSV] Импорт товаров поставщика');
    }

    /**
     * @return array
     */
    public function getAvailableFields()
    {
        $fields = parent::getAvailableFields();

        foreach ((new ShopStoreProduct())->attributeLabels() as $key => $name) {
            if (in_array($key, [
                'name',
                'external_id',
                'quantity',
                'purchase_price',
                'selling_price',
            ])) {
                $fields['shop_store_product.' . $key] = $name;
            }
        }

        return $fields;
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            ['shop_store_id', 'integer'],
            ['titles_row_number', 'integer'],
            ['is_save_source_data', 'boolean'],
            ['is_quantity_clean', 'boolean'],
            [['matching'], 'safe'],
            [
                ['matching'],
                function ($attribute) {
                    /*if (!in_array('element.name', $this->$attribute))
                    {
                        $this->addError($attribute, "Укажите соответствие названия");
                    }*/
                },
            ],
        ]);
    }


    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'matching'       => \Yii::t('skeeks/importCsvContent', 'Preview content and configuration compliance'),
            'shop_store_id'       => "Поставщик или склад",
            'titles_row_number'   => \Yii::t('skeeks/importCsvContent', 'Номер строки которая содержит заголовки'),
            'is_save_source_data' => 'Сохранять исходные данные по товару?',
            'is_quantity_clean'   => 'Обнулить количество у товаров которых нет в файле?',
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'is_save_source_data' => 'Если выбрано да, то все исходные данные по товару будут сохранены в специальное поле.',
        ]);
    }


    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        parent::renderConfigForm($form);

        echo $form->field($this, 'titles_row_number');

        echo $form->field($this, 'is_save_source_data')->listBox(\Yii::$app->formatter->booleanFormat, [
            'size' => 1,
        ]);
        echo $form->field($this, 'is_quantity_clean')->listBox(\Yii::$app->formatter->booleanFormat, [
            'size' => 1,
        ]);
        echo $form->field($this, 'shop_store_id')->listBox(
            ArrayHelper::merge(['' => ' - '], ArrayHelper::map(ShopStore::find()->cmsSite()->all(), 'id', 'name')), [
            'size'             => 1,
            'data-form-reload' => 'true',
        ]);

        if ($this->shop_store_id && $this->rootFilePath && file_exists($this->rootFilePath)) {
            echo '11111';
            echo $form->field($this, 'matching')->widget(
                \skeeks\cms\importCsv\widgets\MatchingInput::className(),
                [
                    'columns' => $this->getAvailableFields(),
                ]
            );
        }
    }



    /**
     * @param $number
     * @param $row
     *
     * @return CsvImportRowResult
     */
    public function import($number, $row)
    {
        $result = new CsvImportRowResult();

        try {
            $element = $this->_initElement($number, $row);
            $this->_initElementData($number, $row, $element);

            $isUpdate = $element->isNewRecord ? false : true;

            if (!$element->save()) {
                throw new Exception("Ошибка сохранения данных элемента: ".print_r($element->errors, true));
            }

            if ($this->is_save_source_data) {
                $data = $this->getRowDataWithHeaders($row);
                $element->external_data = $data;

                if (!$element->save()) {
                    throw new Exception('Свойство магазина не сохранено: '.Json::encode($element->errors));
                }
            }

            $result->data = $this->matching;
            $result->message = ($isUpdate === true ? "Элемент обновлен" : 'Элемент создан');

            //$element->relatedPropertiesModel->initAllProperties();
            //$rp = Json::encode($element->relatedPropertiesModel->toArray());
            $rp = '';
            $result->html = <<<HTML
Элемент: {$element->id}
HTML;
            //unset($element->relatedPropertiesModel);
            unset($element);

        } catch (\Exception $e) {

            $result->success = false;
            $result->message = $e->getMessage();

            //if (!$element->isNewRecord) {
                 $result->html = <<<HTML
Элемент: {$e->getMessage()}
HTML;
            //}

        }


        return $result;
    }


    /**
     * @return ShopStore
     */
    public function getShopStore()
    {
        $query = ShopStore::find()->where(['id' => $this->shop_store_id])->one();
        return $query;
    }

    /**
     * Загрузка данных из строки в модель элемента
     *
     * @param                   $number
     * @param                   $row
     * @param CmsContentElement $element
     *
     * @return $this
     */
    protected function _initElementData($number, $row, ShopStoreProduct $element)
    {

        foreach ($this->matching as $number => $fieldName) {
            //Выбрано соответствие

            $is_update_rewrite = true;

            if ($fieldName) {

                if (is_array($fieldName)) {
                    $is_update_rewrite = (bool) ArrayHelper::getValue($fieldName, 'is_update_rewrite');
                    $fieldName = $fieldName['code'];
                }
                
                if ($element->isNewRecord) {
                    $this->_initModelByField($element, $fieldName, $row[$number]);
                } else {
                    if ($is_update_rewrite) {
                        $this->_initModelByField($element, $fieldName, $row[$number]);
                    }
                }

            }
        }

        return $this;
    }

    protected function _initModelByField(ShopStoreProduct &$cmsContentElement, $fieldName, $value)
    {
        if (strpos("field_".$fieldName, 'shop_store_product.')) {
            $realName = str_replace("shop_store_product.", "", $fieldName);

            if (in_array($realName, ['purchase_price', 'selling_price', 'quantity'])) {
                $value = trim(str_replace(",", ".", $value));
                $value = str_replace(" ", "", $value);
                $value = (float) $value;
            }

            $cmsContentElement->{$realName} = $value;
        }
    }

    /**
     * @param $number
     * @param $row
     * @return ShopStoreProduct
     * @throws Exception
     */
    protected function _initElement($number, $row)
    {
        if (!$this->unique_field) {
            $element = new ShopStoreProduct();
            $element->shop_store_id = $this->shop_store_id;
        } else {
            $uniqueValue = trim($this->getValue($this->unique_field, $row));

            if ($uniqueValue) {
                if (strpos("field_".$this->unique_field, 'shop_store_product.')) {
                    $realName = str_replace("shop_store_product.", "", $this->unique_field);
                    $element = ShopStoreProduct::find()
                        ->where([$realName => $uniqueValue])
                        ->andWhere(["shop_store_id" => $this->shop_store_id])
                    ->one();
                }
            } else {
                throw new Exception('Не задано уникальное значение');
            }

            if (!$element) {
                $element = new ShopStoreProduct();
                $element->shop_store_id = $this->shop_store_id;
            }
        }

        return $element;
    }

    /**
     * @param       $code
     * @param array $row
     *
     * @return null
     */
    public function getValue($code, $row = [])
    {
        $number = $this->getColumnNumber($code);

        if ($number !== null) {
            /*$model = new DynamicModel();
            $model->defineAttribute('value', $row[$number]);
            $model->addRule(['value'], '');*/
            return $row[$number];
        }

        return null;
    }

    /**
     * @param $code
     *
     * @return int|null
     */
    public function getColumnNumber($code)
    {
        //if (in_array($code, $this->matching))
        //{
        foreach ($this->matching as $number => $codeValue) {
            if (is_array($codeValue)) {
                $codeValue = $codeValue['code'];
            }

            if ($codeValue == $code) {
                return (int)$number;
            }
        }
        //}

        return null;
    }

    public function getUnique_field()
    {
        if (!$this->matching) {
            return null;
        }

        foreach ((array)$this->matching as $key => $columnSetting) {
            if (is_array($columnSetting)) {
                if (isset($columnSetting['unique']) && $columnSetting['unique']) {
                    return $columnSetting['code'];
                }
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    public function beforeExecute()
    {
        //Если задан склад и нужно удалять наличие которых нет в файле
        if ($this->is_quantity_clean) {
            if ($this->shop_store_id) {
                if ($updated = ShopStoreProduct::updateAll(['quantity' => 0], ['shop_store_id' => $this->shop_store_id])) {
                    $this->getResult()->stdout("Обнулено: ".$updated);
                }
            }

            /*if ($updated = ShopProduct::updateAll(['quantity' => 0], ['shop_store_id' => $this->shop_store_id])) {
                $this->getResult()->stdout("Обнулено: ".$updated);
            }*/
        }

        return true;
    }




    public function execute()
    {
        ini_set("memory_limit", "8192M");
        set_time_limit(0);

        $base_memory_usage = memory_get_usage();
        $this->memoryUsage(memory_get_usage(), $base_memory_usage);

        $this->beforeExecute();

        $rows = $this->getCsvColumnsData($this->startRow, $this->endRow);
        $results = [];
        $totalSuccess = 0;
        $totalErrors = 0;

        $this->result->stdout("\tCSV import: c {$this->startRow} по {$this->endRow}\n");
        $this->result->stdout("\t\t\t" . $this->memoryUsage(memory_get_usage(), $base_memory_usage) . "\n");
        sleep(5);

        foreach ($rows as $number => $data) {
            $baseRowMemory = memory_get_usage();
            $result = $this->import($number, $data);
            if ($result->success) {
                $this->result->stdout("\tСтрока: {$number}: {$result->message}\n");
                $totalSuccess++;
            } else {
                $this->result->stdout("\tСтрока: {$number}: ошибка: {$result->message}\n");
                $totalErrors++;
            }

            //$this->result->stdout("\t\t\t memory: " . $this->memoryUsage(memory_get_usage(), $baseRowMemory) . "\n");
            unset($rows[$number]);
            unset($result);
            //$this->result->stdout("\t\t\t memory: " . $this->memoryUsage(memory_get_usage(), $baseRowMemory) . "\n");

            if ($number % 25 == 0) {
                $this->result->stdout("\t\t\t Total memory: " . $this->memoryUsage(memory_get_usage(), $base_memory_usage) . "\n");
            }
            gc_collect_cycles();
            //$results[$number] = $result;
        }

        return $this->result;
    }

    public function memoryUsage($usage, $base_memory_usage)
    {
        return \Yii::$app->formatter->asSize($usage - $base_memory_usage);
    }



    protected $_headersData = [];
    /**
     * @param array $row
     * @return array
     */
    public function getRowDataWithHeaders($row = [])
    {
        $result = [];

        if (!$this->titles_row_number && $this->titles_row_number != "0") {
            return $row;
        }

        $this->titles_row_number = (int) $this->titles_row_number;

        $rows = $this->getCsvColumnsData($this->titles_row_number, $this->titles_row_number);
        if (!$rows) {
            return $row;
        }

        $this->_headersData = array_shift($rows);

        if ($this->_headersData) {
            foreach ($this->_headersData as $key => $value)
            {
                $result[$value] = ArrayHelper::getValue($row, $key);
            }
        } else {
            $result = $row;
        }

        return $result;
    }

}