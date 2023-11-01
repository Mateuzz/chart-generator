<?php

require __DIR__ . '/vendor/autoload.php';
require "Mysql.php";

use CpChart\Data;
use CpChart\Image;

const IMAGE_WIDTH = 1600;
const IMAGE_HEIGHT = 600;
const OUTER_PADDING = 100;

$legendConfig = [ 'Mode' => LEGEND_HORIZONTAL, "R" => 120, "G" => 120, "B" => 180, ];
$barConfig = [ "Rounded" => true, "Surrounding" => 30, "DisplayValues" => true, "DisplayPos" => LABEL_POS_OUTSIDE, "Gradient" => true ];

$db = getDatabase();

$data = [];
$data2 = [];
$dataModelLoading = [];
$dataStartup = [];
$dataCPU = [];
$dataRAM = [];

$libraries = [
    'Three' => 'Three.js',
    'Babylon' => 'Babylon.js',
    'claygl' => 'ClayGL',
    'pc' => 'Playcanvas',
];

$extraGroups = [
    "desert4-e-sombra" => [
        'desert4', 'desert_sombra'
    ]
];

$camps = [
    ["fps_avg", "avg"],
    ["fps_truncated_avg", "truncated"],
    ["fps_max_avg", "max"],
    ["fps_min_avg", "min"],
    ["fps_low", "low"],
];

$modelTestsScenes = [
    'Forte',
    'Floresta',
    'Floresta (Combinado)',
    'Crânio',
    'Deserto'
];

$query = "select ";
foreach ($camps as $camp) {
    $query .= "CAST(avg($camp[0]) as int) $camp[1],";
}
$query .= "library, scene, browser, pc from rendering group by browser, pc, scene, library";

$result = $db->query($query);

while ($row = $result->fetch_assoc()) {
    extract($row);

    $isExtra = false;
    foreach ($extraGroups as $extraGroupName => $extraGroup) {
        if ($extraGroup[0] == $scene || $extraGroup[1] == $scene) {
            $data2[$browser][$extraGroupName][$scene][$library] = [$avg, $max, $min, $low];
            $isExtra = true;
            break;
        }
    }

    if (!$isExtra) {
        $data[$browser][$scene][$pc][$library] = [$avg, $max, $min, $low];
    }
}

/* makeBarCharts($data, $data2, $libraries); */

$cpuTestLabels = [
    'linux' => ['FP', 'FP-C', 'FL', 'FL-COM', 'FL-SO', 'FL-C-FX-S', 'C'],
    'windows' => ['FP', 'FP-C', 'FL', 'FL-28', 'FL-SO', 'FL-C-FX-S', 'C', 'DE'],
];

$dataNPM = [
    'Código' => [
        'Three' => [1165,670,184],
        'Babylon' => [6800,4800,1056],
        'claygl' => [870,440,124],
        'pc' => [2500,1400,372],
    ],
    'NPM' => [
        'Three' => [32, 32],
        'Babylon' => [70, 194],
        'claygl' => [12, 16],
        'pc' => [52, 52],
    ],
];


/* makeNPMCharts($dataNPM, $libraries); */

function makeNPMCharts($data, $libraries) {
    $npmTestLabels = [
        'NPM' => ['Principal', 'Total'],
        'Código' => ['Bundled', 'Minificado', 'Minificado + GZIP'],
    ];

    $axisNames = [
        'NPM' => "Tamanho do Repositório NPM (MB)",
        'Código' => "Tamanho do Còdigo-Fonte (KB)",
    ];

    global $barConfig, $legendConfig;

    $image = getImage(IMAGE_WIDTH, IMAGE_HEIGHT);
    $totalWidth = (IMAGE_WIDTH - (OUTER_PADDING * 3));
    $width = 0.6 * $totalWidth;
    $height = IMAGE_HEIGHT - 60 - 80;
    $cursor = getCursor();
    $settings = [];

    cursorMove($cursor, OUTER_PADDING, 60);

    foreach ($data as $infoName => $infoData) {
        $data = new Data();

        foreach ($libraries as $lib => $libName) {
            $data->addPoints($infoData[$lib], $libName);
        }

        $data->addPoints($npmTestLabels[$infoName], 'types');
        $data->setAbscissa('types');
        $data->setAxisName(0, $axisNames[$infoName]);

        cursorSetSize($cursor, $width, $height);
        imageStartDraw($image, $cursor, $data, $settings);
        $settings = [
            'Pos' => SCALE_POS_TOPBOTTOM,
            'Factors' => [40]
        ];
        $image->drawBarChart($barConfig);
        cursorMove($cursor, $width + OUTER_PADDING);
        $width = 0.4 * $totalWidth;
    }

    $image->drawLegend(OUTER_PADDING, IMAGE_HEIGHT - 30, $legendConfig);
    $image->autoOutput("./npm.png");
}

$dataCPU = [
    'linux' => [
        'CPU' => [
            'pc' => [15,VOID,45,20,30,VOID,11],
            'Three' => [15,15,50,15,50,50,15],
            'Babylon' => [15,15,50,15,55,50,20],
            'claygl' => [20,30,50,15,100,100,20],
        ],
        'RAM' => [
            'pc' => [12,VOID,41,11,38,VOID,11],
            'Three' => [6,10,20,6,8,25,8],
            'Babylon' => [15,25,45,16,30,48,26],
            'claygl' => [6,13,17,8,20,29,24],
        ]
    ],
    'windows' => [
        'CPU' => [
            'pc' => [5,VOID,20,30,15,VOID,5,100],
            'Three' => [5,5,20,30,20,45,5,100],
            'Babylon' => [5,5,35,20,45,20,5,100],
            'claygl' => [5,5,30,45,30,90,5,100],
        ],
        'RAM' => [
            'pc' => [20,VOID,37,52,36,VOID,40,100],
            'Three' => [6.6,8,20,33,30,36,24,42],
            'Babylon' => [16,18,48,45,50,67,27,126],
            'claygl' => [15,28,28,30,33,37,22,35],
        ]
    ]
    ];

    $cpuAxisNames = [
        'CPU' => 'CPU (%)',
        'RAM' => 'RAM (MB)'
    ];

    $cpuBarConfig = array_merge($barConfig, ["DisplayValues" => false]);


    foreach ($dataCPU as $pcName => $pc) {
        $image = getImage(IMAGE_WIDTH, IMAGE_HEIGHT);
        $totalWidth = (IMAGE_WIDTH - (OUTER_PADDING * 3));
        $width = 0.6 * $totalWidth;
        $height = IMAGE_HEIGHT - 60 - 80;
        $cursor = getCursor();
        $settings = [];

        cursorMove($cursor, OUTER_PADDING, 60);

        foreach ($pc as $infoName => $infoData) {
            $data = new Data();

            foreach ($libraries as $lib => $libName) {
                $data->addPoints($infoData[$lib], $libName);
            }

            $data->addPoints($cpuTestLabels[$pcName], 'scenes');
            $data->setAbscissa('scenes');

            $data->setAxisName(0, $cpuAxisNames[$infoName]);

            cursorSetSize($cursor, $width, $height);
            imageStartDraw($image, $cursor, $data, $settings);
            $settings = [
                'Pos' => SCALE_POS_TOPBOTTOM,
                'Factors' => [10]
            ];
            $image->drawBarChart($cpuBarConfig);
            cursorMove($cursor, $width + OUTER_PADDING);
            $width = 0.4 * $totalWidth;
        }

        $image->drawLegend(OUTER_PADDING, IMAGE_HEIGHT - 30, $legendConfig);
        $image->autoOutput("./cpu/$pcName.png");
    }

$query = "select avg(ms) ms, library, scene, pc from model_loading group by pc, scene, library order by scene";
$result = $db->query($query);

while ($row = $result->fetch_assoc()) {
    extract($row);
    $dataModelLoading[$pc]['model_loading'][$library][$scene] = (int)$ms;
}

$query = "select avg(ms) ms, library, scene, pc from startup group by pc, library";
$result = $db->query($query);

while ($row = $result->fetch_assoc()) {
    extract($row);
    $dataModelLoading[$pc]['startup'][$library][0] = (int)$ms;
}

$query = "select min(ms) ms, library, scene, pc from startup group by pc, library";
$result = $db->query($query);

while ($row = $result->fetch_assoc()) {
    extract($row);
    $dataModelLoading[$pc]['startup'][$library][1] = (int)$ms;
}

$modelLoadingTestLabels = [
    'model_loading' => ['', 'Floresta', 'Deserto', 'Crânio'],
    'startup' => ['Inicialização'],
];

$modelLoadingTestLabels = [
    'linux' => [
        'startup' => ['Média', 'Mínimo'],
        'model_loading' => ['Floresta', 'Floresta (Combinado)', 'Forte Pirata', 'Crãnio'],
    ],
    'windows' => [
        'startup' => ['Média', 'Mínimo'],
        'model_loading' => ['Deserto', 'Floresta', 'Floresta (Combinado)', 'Forte Pirata', 'Crãnio'],
    ],
];

foreach ($dataModelLoading as $pcName => $pc) {
    $image = getImage(IMAGE_WIDTH, IMAGE_HEIGHT);
    $totalWidth = (IMAGE_WIDTH - (OUTER_PADDING * 3));
    $width = 0.6 * $totalWidth;
    $height = IMAGE_HEIGHT - 50 - 80;
    $cursor = getCursor();
    $settings = [
        'DisplayValues' => true,
        'Factors' => [1000, 500]
    ];

    $barConfig = array_merge($barConfig, [
        "DisplayValues" => true
    ]);

    cursorMove($cursor, OUTER_PADDING, 50);

    foreach ($pc as $infoName => $infoData) {
        $data = new Data();

        foreach ($libraries as $lib => $libName) {
            $data->addPoints($infoData[$lib], $libName);
        }

        $data->addPoints($modelLoadingTestLabels[$pcName][$infoName], 'labels');
        $data->setAbscissa('labels');
        $data->setAxisName(0, [
            'startup' => 'Inicialização (ms)',
            'model_loading' => 'Carregamento de Modelos (ms)'
        ][$infoName]);

        cursorSetSize($cursor, $width, $height);
        imageStartDraw($image, $cursor, $data, $settings);
        $settings = [
            'Pos' => SCALE_POS_TOPBOTTOM,
        ];
        $image->drawBarChart($barConfig);
        cursorMove($cursor, $width + OUTER_PADDING);
        $width = 0.4 * $totalWidth;
    }

    $image->drawLegend(OUTER_PADDING, IMAGE_HEIGHT - 30, $legendConfig);
    $image->autoOutput("./loading/$pcName.png");
}

$query = "select avg(ms) ms, scene, pc, library from model_loading group by scene, pc, library";
$result = $db->query($query);

while ($row = $result->fetch_assoc()) {
    extract($row);
    $dataModelLoading[$scene][$pc][$library] = $ms;
}

$query = "select avg(ms) ms, scene, pc, library from startup group by scene, pc, library";
$result = $db->query($query);

while ($row = $result->fetch_assoc()) {
    extract($row);
    $dataStartup[$pc][$library] = $ms;
}


function makeBarCharts($data, $data2, $libraries) {
    foreach ($data2 as $browserName => $browser) {
        foreach ($browser as $extraGroupName => $extraGroup) {
            makeRenderingChart("./images/$browserName-$extraGroupName.png", $extraGroup, $libraries);
        }
    }

    foreach ($data as $browserName => $browser) {
        foreach ($browser as $sceneName => $scene) {
            makeRenderingChart("./images/$browserName-$sceneName.png", $scene, $libraries);
        }
    }
}

function getImage($width, $height) {
    $image = new Image(IMAGE_WIDTH, IMAGE_HEIGHT);
    $image->drawRectangle(1, 1, IMAGE_WIDTH - 1, IMAGE_HEIGHT -1, makeArrayColor(0, 0, 0));

    $Settings = array("R" => 170, "G" => 183, "B" => 87, "Dash" => 1, "DashR" => 190, "DashG" => 203, "DashB" => 107);
    $image->drawFilledRectangle(0, 0, IMAGE_WIDTH, IMAGE_HEIGHT, $Settings);

    $Settings = array("StartR" => 219, "StartG" => 231, "StartB" => 139, "EndR" => 1, "EndG" => 138, "EndB" => 68, "Alpha" => 50);
    $image->drawGradientArea(0, 0, IMAGE_WIDTH, IMAGE_HEIGHT, DIRECTION_VERTICAL, $Settings);

    return $image;
}

function getData(array $pointData, ?string $abcissaName) {
    $data = new Data();
    foreach ($pointData as $points) {
        $data->addPoints($points['values'], $points['name']);
    }
    if (!is_null($abcissaName))
        $data->setAbscissa($abcissaName);
    return $data;
}

function getCursor() {
    return [
        'xStart' => 0,
        'yStart' => 0,
    ];
}

function cursorSetSize(&$layout, $width, $height) {
    $layout['xEnd'] = $layout['xStart'] + $width;
    $layout['yEnd'] = $layout['yStart'] + $height;
}

function cursorMove(&$layout, $x = 0, $y = 0) {
    $layout['xStart'] += $x;
    $layout['yStart'] += $y;
}

function imageStartDraw(Image $image, &$cursor, $data, $settings =[]) {
    $image->setDataSet($data);
    $drawSettings = [
        "CycleBackground" => true,
        "DrawSubTicks" => true,
        "GridR" => 0,
        "GridG" => 0,
        "GridB" => 0,
        "GridAlpha" => 20,
        "Mode" => SCALE_MODE_START0,
    ];

    $drawSettings = array_merge($drawSettings, $settings);

    extract($cursor);

    $image->setFontProperties(["FontName" => "verdana.ttf", "FontSize" => 12]);
    $image->setGraphArea($xStart, $yStart, $xEnd, $yEnd);
    $image->drawScale($drawSettings);
    $image->setShadow(true, ["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 10]);
}


function makeArrayColor($R, $G, $B) {
    return compact(["R", "G", "B"]);
}


function makeRenderingChart($filename, $group, $libraries) {
    $count = count($group);
    $image = getImage(IMAGE_WIDTH, IMAGE_HEIGHT);
    $width = (IMAGE_WIDTH - OUTER_PADDING * ($count + 1)) / $count;
    $height = IMAGE_HEIGHT - 30 - 80;
    $cursor = getCursor();
    global $legendConfig, $barConfig;

    $renderingTestLabels = ['Média', 'Máximo', 'Mínimo', '1% Low'];

    cursorMove($cursor, OUTER_PADDING, 30);

    foreach ($group as $chart) {
        $data = new Data();

        foreach ($libraries as $lib => $libFullName) {
            $data->addPoints(isset($chart[$lib]) ? $chart[$lib] : VOID, $libFullName);
        }

        $data->addPoints($renderingTestLabels, 'fps');
        $data->setAbscissa('fps');
        /* $data->setAxisName(0, "FPS"); */

        cursorSetSize($cursor, $width, $height);
        imageStartDraw($image, $cursor, $data);
        $image->drawBarChart($barConfig);
        cursorMove($cursor, $width + OUTER_PADDING);
    }

    $image->drawLegend(OUTER_PADDING, IMAGE_HEIGHT - 30, $legendConfig);
    $image->autoOutput($filename);
}
