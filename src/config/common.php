<?php
return [

    'components' =>
    [
        'cmsImport' => [
            'handlers'     =>
            [
                'skeeks\cms\importCsvShopContent\ImportCsvShopContentHandler' =>
                [
                    'class' => 'skeeks\cms\importCsvShopContent\ImportCsvShopContentHandler'
                ],
                'skeeks\cms\importCsvShopContent\ImportCsvShopStoreProductHandler' =>
                [
                    'class' => 'skeeks\cms\importCsvShopContent\ImportCsvShopStoreProductHandler'
                ]
            ]
        ],

        'i18n' => [
            'translations' =>
            [
                'skeeks/importCsvShopContent' => [
                    'class'             => 'yii\i18n\PhpMessageSource',
                    'basePath'          => '@skeeks/cms/importCsvShopContent/messages',
                    'fileMap' => [
                        'skeeks/importCsvShopContent' => 'main.php',
                    ],
                ]
            ]
        ]
    ]
];