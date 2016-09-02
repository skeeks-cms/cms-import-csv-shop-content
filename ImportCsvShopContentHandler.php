<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 29.08.2016
 */
namespace skeeks\cms\importCsvShopContent;
use skeeks\cms\importCsvContent\ImportCsvContentHandler;
use skeeks\cms\models\CmsContent;
use skeeks\cms\shop\models\ShopContent;
use yii\db\ActiveQuery;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;

/**
 * Class CsvContentHandler
 *
 * @package skeeks\cms\importCsvContent
 */
class ImportCsvShopContentHandler extends ImportCsvContentHandler
{
    public $matchingChild = [];

    public function init()
    {
        parent::init();

        $this->name = \Yii::t('skeeks/importCsvShopContent', '[CSV] Import of products');
    }

    /**
     * @return array
     */
    public function getAvailableFields()
    {
        $data = parent::getAvailableFields();

        return $data;
    }


    public function getAvailableChildFields()
    {
        $element = new CmsContentElement([
            'content_id' => $this->cmsContent->childrenContents
        ]);

        $fields = [];

        foreach ($element->attributeLabels() as $key => $name)
        {
            $fields['element.' . $key] = $name;
        }

        foreach ($element->relatedPropertiesModel->attributeLabels() as $key => $name)
        {
            $fields['property.' . $key] = $name . " [свойство]";
        }

        $fields['image'] = 'Ссылка на главное изображение';

        return array_merge(['' => ' - '], $fields);
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [

            [['matchingChild'], 'safe'],
            [['matchingChild'], function($attribute) {
                /*if (!in_array('element.parent_content_element_id', $this->$attribute))
                {
                    $this->addError($attribute, "Укажите соответствие названия");
                }
                if (!in_array('element.name', $this->$attribute))
                {
                    $this->addError($attribute, "Укажите соответствие названия");
                }*/
            }]
        ]);
    }


    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'matchingChild'          => \Yii::t('skeeks/importCsvShopContent', 'Соответствие торговых предложений'),
        ]);
    }


    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        $this->renderCsvConfigForm($form);

        echo $form->field($this, 'content_id')->listBox(
            array_merge(['' => ' - '], CmsContent::getDataForSelect(true, function(ActiveQuery $activeQuery)
            {
                //$activeQuery->andWhere([''])
            })), [
            'size' => 1,
            'data-form-reload' => 'true'
        ]);

        if ($this->content_id && $this->rootFilePath && file_exists($this->rootFilePath))
        {
            echo $form->field($this, 'matching')->widget(
                \skeeks\cms\importCsv\widgets\MatchingInput::className(),
                [
                    'columns' => $this->getAvailableFields()
                ]
            );

            echo $form->field($this, 'unique_field')->listBox(
                array_merge(['' => ' - '], $this->getAvailableFields()), [
                'size' => 1,
            ]);

            echo $form->field($this, 'matchingChild')->widget(
                \skeeks\cms\importCsv\widgets\MatchingInput::className()
            );

        }
    }
}