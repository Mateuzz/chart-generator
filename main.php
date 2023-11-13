<?php

require __DIR__ . '/vendor/autoload.php';
require "Mysql.php";

use CpChart\Data;
use CpChart\Image;

const IMAGE_WIDTH = 1700;
const IMAGE_HEIGHT = 600;
const OUTER_PADDING = 100;

$legendConfig = ['Mode' => LEGEND_HORIZONTAL, "R" => 255, "G" => 255, "B" => 255, 'BoxWidth' => 16, 'BoxHeight' => 16, "Alpha" => 255, "FontSize" => 18, "Family" => LEGEND_FAMILY_BOX];
$barConfig = ["Rounded" => true, "Surrounding" => 30, "DisplayValues" => false, "DisplayPos" => LABEL_POS_OUTSIDE, "Gradient" => true];

$db = getDatabase();

$data = [];
$data2 = [];
$datav2 = [];
$dataModelLoading = [];
$dataCPU = [];
$dataRAM = [];

$libraries = [
    'Three' => 'Three.js',
    'Babylon' => 'Babylon.js',
    'babylon_fast' => 'Babylon.js (Optimized)',
    'pc' => 'Playcanvas (Engine)',
    'pc-editor' => 'Playcanvas (Editor)',
];

$palletes = [
    'Three' => ['R' => 250, 'G' => 230, 'B' => 80,],
    'Babylon' => ['R' => 240, 'G' => 050, 'B' => 040,],
    'babylon_fast' => ['R' => 255, 'G' => 150, 'B' => 30,],
    /* 'pc-editor' => ['R' => 250, 'G' => 250, 'B' => 250,], */
    'pc-editor' => ['R' => 100, 'G' => 140, 'B' => 120,],
    'pc' => ['R' => 69, 'G' => 71, 'B' => 114,],
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

$query = "select ";
foreach ($camps as $camp) {
    $query .= "CAST(avg($camp[0]) as int) $camp[1],";
}
    $query .= "library, scene, browser, pc from rendering where scene not regexp 'fastest' group by browser, pc, scene, library";

$result = $db->query($query);

$groups = [
    'windows' => [
        'g1' => ['pirate_perto', 'pirate_longe', 'ion', 'floresta_merged', 'skull', 'ilha'],
        'g2' => explode(' ', 'floresta_perto floresta_longe floresta_sombra lab_longe lab_perto'),
        'g3' => explode(' ', 'desert car brokencar car_tank car_many car_clone'),
        /* 'g2' => ['floresta_perto', 'floresta_longe', 'floresta_sombra', 'lab_longe', 'lab_perto', 'desert', 'desert_sombra'], */
    ],
    'linux' => [
        'g1' => [
            'pirate_perto', 'pirate_longe', 'ion', 'floresta_merged', 'skull', 'ilha',
            'floresta_perto', 'floresta_longe', 'floresta_sombra', 'lab_longe', 'lab_perto'
        ]
    ]
];

$cpuLabelMap = [
    'pirate' => 'Forte',
    'floresta' => 'Vale',
    'floresta_merged' => 'Vale (batch)',
    'floresta_sombra' => 'Vale + Sombra',
    'lab' => 'Lab',
    'ion' => 'Propulsor',
    'ilha' => 'Ilha',
    'skull' => 'Crânio',
    'desert' => 'Deserto',
    /* 'car' => 'Carro 1', */
    /* 'brokencar' => 'Carro 2', */
    /* 'car_tank' => 'Tanque', */
    /* 'car_many' => 'Carros', */
    /* 'car_clone' => 'Carros 2', */
];

$cpuGroups = [
    'linux' => [
        'pirate', 'ion', 'floresta_merged', 'skull', 'ilha', 'floresta', 'floresta_sombra', 'lab'
    ],
];
$cpuGroups['windows'] = array_merge($cpuGroups['linux'], ['desert']);

while ($row = $result->fetch_assoc()) {
    extract($row);

    $scene = str_replace("_fast", "", $scene);
    if (isset($groups[$pc])) {
        foreach ($groups[$pc] as $groupName => $group) {
            if (array_search($scene, $group) !== false) {
                $group = $groupName;
                break;
            }
        }
    }
    $datav2[$browser][$pc][$groupName][$library][$scene] = $avg;
}

$dataNPM = [
    'Código' => [
        'Three' => [1165, 670, 184],
        'Babylon' => [6800, 4800, 1056],
        'pc' => [2500, 1400, 372],
    ],
    'NPM' => [
        'Three' => [32, 32],
        'Babylon' => [70, 194],
        'pc' => [52, 52],
    ],
];

function makeNPMCharts($data, $libraries)
{
    $npmTestLabels = [
        'NPM' => ['Principal', 'Total'],
        'Código' => ['Bundled', 'Minificado', 'Minificado + GZIP'],
    ];

    $axisNames = [
        'NPM' => "Tamanho do Repositório NPM (MB)",
        'Código' => "Tamanho do Còdigo-Fonte (KB)",
    ];

    global $barConfig, $legendConfig, $palletes;

    $npmBarConfig = array_merge($barConfig, [
        "DisplayValues" => true
    ]);

    $image = getImage(IMAGE_WIDTH, IMAGE_HEIGHT);
    $outerPadding = OUTER_PADDING - 20;
    $innerPadding = OUTER_PADDING;
    $totalWidth = (IMAGE_WIDTH - ($outerPadding * 2 + $innerPadding));
    $width = 0.6 * $totalWidth;
    $height = IMAGE_HEIGHT - 60 - 80;
    $cursor = getCursor();
    $settings = [];

    cursorMove($cursor, $outerPadding, 60);

    $libraries = [
        'Three' => 'Three.js',
        'Babylon' => 'Babylon.js',
        'pc' => 'Playcanvas',
        'pc-editor' => 'Playcanvas',
    ];

    foreach ($data as $infoName => $infoData) {
        $data = new Data();

        foreach ($libraries as $lib => $libName) {
            if (isset($infoData[$lib]))
                $data->addPoints($infoData[$lib], $libName);
        }
        foreach ($palletes as $lib => $colors)
            $data->setPalette($libraries[$lib], $colors);

        $data->addPoints($npmTestLabels[$infoName], 'types');
        $data->setAbscissa('types');
        $data->setAxisName(0, $axisNames[$infoName]);

        cursorSetSize($cursor, $width, $height);
        imageStartDraw($image, $cursor, $data, $settings);
        $settings = [
            'Pos' => SCALE_POS_TOPBOTTOM,
            'Factors' => [40]
        ];
        $image->drawBarChart($npmBarConfig);
        cursorMove($cursor, $width + $innerPadding);
        $width = 0.4 * $totalWidth;
    }

    $image->drawLegend(OUTER_PADDING, IMAGE_HEIGHT - 30, $legendConfig);
    $image->autoOutput("./npm.png");
}

$dataCPU = [
    'linux' => [
        'CPU' => [
            'Three' => [10,5,15,15,40,55,55,35],
            'Babylon' => [10,10,15,20,50,50,60,45],
            'pc' => [10,10,15,15,35,45,55,35],
            'pc-editor' => [10,10,15,20,35,45,55,40],
        ],
        'RAM' => [
            'Three' => [5.6,5,5,6.6,14.7,8.4,17,16],
            'Babylon' => [17,14,12,17,25,29,25,44],
            'pc' => [10,12,7,12.5,15,17,19,12.8],
            'pc-editor' => [9,8.5,14,11,14,17.6,22,14],
        ]
    ],
    'windows' => [
        'CPU' => [
            'Three' => [5,5,5,5,10,20,30,15,100],
            'Babylon' => [5,5,5,5,15,20,40,20,100],
            'pc' => [5,5,5,5,10,15,30,15,100],
            'pc-editor' => [5,5,5,5,10,15,30,10,100],
        ],
        'RAM' => [
            'Three' => [6.3,8,8,11,15,28,17,27,40],
            'Babylon' => [15,14,13,23,32,30,31,35,122],
            'pc' => [10,12,11,18,31,21,26,33,101],
            'pc-editor' => [8,9,15,14,30,36,36,30,124],
        ]
    ]
];

function makeCpuCharts($dataCPU, $libraries)
{
    global $barConfig, $legendConfig, $palletes;
    global $cpuGroups, $cpuLabelMap;

    $cpuAxisNames = [
        'CPU' => 'CPU (%)',
        'RAM' => 'RAM (MB)'
    ];

    $cpuBarConfig = array_merge($barConfig, ["DisplayValues" => false]);

    $outerPadding = OUTER_PADDING - 20;
    $width = IMAGE_WIDTH - $outerPadding * 2;
    $height = IMAGE_HEIGHT - 60 - 80;
    $settings = [ 'MinDivHeight' => 20, ];

    foreach ($dataCPU as $pcName => $pc) {
        foreach ($pc as $infoName => $infoData) {
            $image = getImage(IMAGE_WIDTH, IMAGE_HEIGHT);
            $cursor = getCursor();
            cursorMove($cursor, $outerPadding, 60);

            $i = 6;
            $data = new Data();

            foreach ($libraries as $lib => $libName) {
                $data->addPoints($infoData[$lib], $libName);
                $data->setSerieTicks($libName, $i += 1);
                $data->setSerieWeight($libName, $i / 8);
            }

            foreach ($palletes as $lib => $colors) {
                $data->setPalette($libraries[$lib], $colors);
            }

            $data->addPoints(array_map(fn($item) => $cpuLabelMap[$item], $cpuGroups[$pcName]), 'scenes');
            $data->setAbscissa('scenes');
            $data->setAxisName(0, $cpuAxisNames[$infoName]);

            cursorSetSize($cursor, $width, $height);
            imageStartDraw($image, $cursor, $data, $settings);

            $image->drawLineChart($cpuBarConfig);
            $image->drawLegend(OUTER_PADDING, IMAGE_HEIGHT - 30, $legendConfig);
            $image->autoOutput("./cpu/$infoName-$pcName.png");
        }
    }
}

$query = "select avg(ms) ms, library, scene, pc from model_loading group by pc, scene, library";
$result = $db->query($query);

while ($row = $result->fetch_assoc()) {
    extract($row);
    $dataModelLoading[$pc][$library][$scene] = (int)$ms;
}

$query = "select avg(ms) ms, library, scene, pc from startup group by pc, library";
$result = $db->query($query);

while ($row = $result->fetch_assoc()) {
    extract($row);
    $dataModelLoading[$pc][$library]['startup'] = (int)$ms;
}

function makeLoadingChart($dataModelLoading, $libraries)
{
    $labelMap = [
        'startup' => 'Inicialização',
        'pirate' => 'Forte Pirata',
        'ion' => 'Propulsor',
        'floresta_merged' => 'Vale (batch)',
        'skull' => 'Crânio',
        'ilha' => 'Ilha',
        'floresta' => 'Vale',
        'lab' => 'Lab',
        'desert' => 'Deserto',
    ];

    global $barConfig, $legendConfig, $palletes;

    $LoadingbarConfig = array_merge($barConfig, [
        "DisplayValues" => true
    ]);

    foreach ($dataModelLoading as $pcName => $pc) {
        $image = getImage(IMAGE_WIDTH, IMAGE_HEIGHT);
        $outerPadding = OUTER_PADDING - 20;
        $width = (IMAGE_WIDTH - ($outerPadding * 2));
        $height = IMAGE_HEIGHT - 60 - 80;
        $cursor = getCursor();
        $settings = [];

        cursorMove($cursor, $outerPadding, 50);
        $data = new Data();

        $usedScenes = [];

        foreach ($libraries as $lib => $libName) {
            $points = [];
            foreach ($labelMap as $scene => $labelName) {
                if (isset($pc[$lib][$scene])) {
                    $points[] = $pc[$lib][$scene];
                    $usedScenes[$scene] = true;
                }
            }
            $data->addPoints($points, $libName);
        }

        $labels = [];
        foreach ($labelMap as $scene => $labelName)
            if (isset($usedScenes[$scene]))
                $labels[] = $labelName;

        foreach ($palletes as $lib => $colors)
            $data->setPalette($libraries[$lib], $colors);

        $data->addPoints($labels, 'scenes');
        $data->setAbscissa('scenes');
        $data->setAxisName(0, "Tempo de Carregamento (ms)");

        cursorSetSize($cursor, $width, $height);
        imageStartDraw($image, $cursor, $data, $settings);
        $settings = [
            'Pos' => SCALE_POS_TOPBOTTOM,
            'Factors' => [60]
        ];
        $image->drawBarChart($LoadingbarConfig);

        $image->drawLegend(OUTER_PADDING, IMAGE_HEIGHT - 30, $legendConfig);
        $image->autoOutput("./loading/$pcName.png");
    }
}

function makeBarCharts($dataAvg, $libraries)
{
    foreach ($dataAvg as $browserName => $browser) {
        makeAvgChart("./avgs/$browserName", $browser, $libraries);
    }
}

function getImage($width, $height)
{
    $image = new Image($width, $height);
    /* $image->drawRectangle(0, 0, $width - 0, $height - 0, makeArrayColor(0, 0, 0)); */

    $Settings = array("R" => 255, "G" => 255, "B" => 255, 'A' => 255);
    $image->drawFilledRectangle(0, 0, $width, $height, $Settings);

    /* $Settings = array("StartR" => 220, "StartG" => 220, "StartB" => 220, "EndR" => 100, "EndG" => 100, "EndB" => 100, "Alpha" => 50); */
    /* $image->drawGradientArea(0, 0, $width, $height, DIRECTION_VERTICAL, $Settings); */

    return $image;
}

function getData(array $pointData, ?string $abcissaName)
{
    $data = new Data();
    foreach ($pointData as $points) {
        $data->addPoints($points['values'], $points['name']);
    }
    if (!is_null($abcissaName))
        $data->setAbscissa($abcissaName);
    return $data;
}

function getCursor()
{
    return [
        'xStart' => 0,
        'yStart' => 0,
    ];
}

function cursorSetSize(&$layout, $width, $height)
{
    $layout['xEnd'] = $layout['xStart'] + $width;
    $layout['yEnd'] = $layout['yStart'] + $height;
}

function cursorMove(&$layout, $x = 0, $y = 0)
{
    $layout['xStart'] += $x;
    $layout['yStart'] += $y;
}

function imageStartDraw(Image $image, &$cursor, $data, $settings = [])
{
    $image->setDataSet($data);
    $drawSettings = [
        "CycleBackground" => true,
        "DrawSubTicks" => true,
        "GridR" => 50,
        "GridG" => 50,
        "GridB" => 50,
        "GridAlpha" => 60,
        "Mode" => SCALE_MODE_START0,
        "DrawArrows" => true,
        "ArrowSize" => 20,
        "BackgroundR1" => 220,
        "BackgroundG1" => 240,
        "BackgroundB1" => 230,
        "BackgroundR2" => 180,
        "BackgroundG2" => 200,
        "BackgroundB2" => 200,
    ];

    $drawSettings = array_merge($drawSettings, $settings);

    extract($cursor);

    $image->setFontProperties(["FontName" => "verdana.ttf", "FontSize" => 12]);
    $image->setGraphArea($xStart, $yStart, $xEnd, $yEnd);
    $image->drawScale($drawSettings);
    $image->setShadow(true, ["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 10]);
}


function makeArrayColor($R, $G, $B)
{
    return compact(["R", "G", "B"]);
}


function makeAvgChart($filename, $browser, $libraries)
{
    global $legendConfig, $barConfig, $palletes;

    $localBarConfig = array_merge($barConfig, [
        'DisplayValues' => true,
    ]);

    global $groups;

    $labelMap = [
        'pirate_perto' => 'Forte (perto)',
        'pirate_longe' => 'Forte (longe)',
        'floresta_perto' => 'Vale (perto)',
        'floresta_longe' => 'Vale (longe)',
        'floresta_merged' => 'Vale (batch)',
        'floresta_sombra' => 'Vale + sombra',
        'ion' => 'Propulsor',
        'lab_perto' => 'Lab (interior)',
        'lab_longe' => 'Lab (exterior)',
        'ilha' => 'Ilha',
        'skull' => 'Crânio',
        'desert' => 'Deserto',
        'desert_sombra' => 'Deserto + sombra',
        'car' => 'Carro 1',
        'brokencar' => 'Carro 2',
        'car_tank' => 'Tanque',
        'car_many' => 'Carros',
        'car_clone' => 'Carros 2',
    ];

    $axisNames = [
        'linux' => 'Quadros por Segundo',
        'windows' => 'Quadros por Segundo',
    ];

    $outerPadding = OUTER_PADDING - 20;
    $width = IMAGE_WIDTH - $outerPadding * 2;
    $height = IMAGE_HEIGHT - 60 - 80;

    foreach ($browser as $pcName => $pc) {
        foreach ($pc as $groupName => $group) {
            $cursor = getCursor();
            $image = getImage(IMAGE_WIDTH, IMAGE_HEIGHT);
            cursorMove($cursor, $outerPadding, 50);
            $data = new Data();

            foreach ($libraries as $lib => $libFullName) {
                $points = array_map(fn ($item) => $group[$lib][$item] ?: VOID, $groups[$pcName][$groupName]);
                $data->addPoints($points, $libFullName);
            }
            foreach ($palletes as $lib => $colors) {
                $data->setPalette($libraries[$lib], $colors);
            }

            $data->addPoints(array_map(fn ($item) => $labelMap[$item], $groups[$pcName][$groupName]), 'fps');
            $data->setAbscissa('fps');
            $data->setAxisName(0, $axisNames[$pcName]);

            cursorSetSize($cursor, $width, $height);
            imageStartDraw($image, $cursor, $data);
            $image->drawBarChart($localBarConfig);

            $image->drawLegend(OUTER_PADDING, IMAGE_HEIGHT - 30, $legendConfig);
            $image->autoOutput("$filename-$pcName-$groupName.png");
        }
    }
}

/* makeNPMCharts($dataNPM, $libraries); */
makeBarCharts($datav2, $libraries);
/* makeLoadingChart($dataModelLoading, $libraries); */
/* makeCpuCharts($dataCPU, $libraries); */
