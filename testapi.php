#!/usr/bin/env php
<?php

$test_file = isset($argv[1]) ? $argv[1] : '';

if ($argc < 3) {
    $file = basename(__FILE__);
    printf("Usage: ./$file [api.pl] [api_id]\n");
}

if ($argc < 2 || !file_exists($test_file)) {
    exit(1);
} else {
    $test_content = file_get_contents($test_file);
}


$base_header = [];
$base_get = [];
// 解析全局配置
$base_config = [
    "BASE_URL" => "http://127.0.0.1",
];
preg_match_all("/\s*?@\s*?(\w*)\s*?:\s*+(.*+)/", $test_content, $base_config_match);
if (!empty($base_config_match)) {
    foreach ($base_config_match[0] as $key => $val) {
        $base_config[$base_config_match[1][$key]] = $base_config_match[2][$key];
    }
    if (isset($base_config['USER_AGENT'])) {
        $base_header['User-Agent'] = $base_config['USER_AGENT'];
    }
}

// 解析header
preg_match("/(.*?)===/s", $test_content, $head_match);
$lines = explode("\n", trim($head_match[1]));

for ($i = 1; $i < sizeof($lines); $i++) {
    if (preg_match("/^---\s*header/", $lines[$i])) {
    // 获取请求参数
        while (++$i < sizeof($lines)) {
            if (empty($lines[$i]) || preg_match("/\s*?#.*+/", $lines[$i])) {
                continue;
            }
            if (preg_match("/\s*+([\w\-]*)\s*+:(.*+)/", $lines[$i], $match)) {
                $base_header[$match[1]] = trim($match[2]);
            } else {
                $i--;
                break;
            }
        }
    } elseif (preg_match("/---\s*get/", $lines[$i])) {
        // 全局get 定义
        while (++$i < sizeof($lines)) {
            if (empty($lines[$i]) || preg_match("/\s*?#.*+/", $lines[$i])) {
                continue;
            }
            if (preg_match("/\s*+([\w-]*)\s*+:(.*+)/", $lines[$i], $match)) {
                $base_get[$match[1]] = trim($match[2]);
            } else {
                $i--;
                break;
            }
        }
    }
}

$api_list = [];
$api_list_match = [];
preg_match("/===(.*+)/is", $test_content, $api_list_match);
$api_list_match = explode("===", $api_list_match[1]);

$api_index = 1;
foreach ($api_list_match as $val) {
    $lines = explode("\n", $val);
    if (sizeof($lines) < 3) {
        printf("api格式不正确\n%s\n", $val);
        exit(1);
    }
    $api = ['header' => []];
    if (preg_match("/Test\s*+:\s*+(.*+)/", $lines[0], $match)) {
        $api['index'] = $api_index++;
        $api['title'] = trim($match[1]);
    } else {
        printf("api格式不正确\n%s\n", $val);
        exit(1);
    }

    for ($i = 1; $i < sizeof($lines); $i++) {
        if (preg_match("/^---\s*?request/", $lines[$i])) {
            // request 请求定义
            while (++$i < sizeof($lines)) {
                if (preg_match("/(GET|POST|DELETE|PUT)\s*+(.*+)/", $lines[$i], $match)) {
                    $api['method'] = $match[1];
                    $api['uri'] = trim($match[2]);
                    break;
                }
            }
            if (empty($api['method']) || empty($api['uri'])) {
                printf("api格式不正确\n%s\n", $val);
            }
            // 获取请求参数
            while (++$i < sizeof($lines)) {
                if (empty($lines[$i]) || preg_match("/\s*?#.*+/", $lines[$i])) {
                    continue;
                }
                if (preg_match("/\s*+(\w*)\s*+:(.*+)/", $lines[$i], $match)) {
                    $api['param'][$match[1]] = trim($match[2]);
                } else {
                    $i--;
                    break;
                }
            }
        } elseif (preg_match("/^---\s*?header/", $lines[$i])) {
            // header 定义
            while (++$i < sizeof($lines)) {
                if (empty($lines[$i]) || preg_match("/\s*?#.*+/", $lines[$i])) {
                    continue;
                }
                if (preg_match("/\s*+([\w-]*)\s*+:(.*+)/", $lines[$i], $match)) {
                    $api['header'][$match[1]] = trim($match[2]);
                } else {
                    $i--;
                    break;
                }
            }
        }
    }
    $api_list[] = $api;
}

$test_index = $argv[2]?? 0;

if ($test_index <= 0 || $test_index > sizeof($api_list)) {
    $max_chars = 0;
    $output = "";
    foreach ($api_list as $value) {
        $line = sprintf("[ %d ] -> %s\n", $value['index'], $value['title']);
        if (strlen($line) > $max_chars) {
            $max_chars = strlen($line);
        }
        $output .= $line;
    }
    printf(str_repeat("*", $max_chars) . "\n");
    printf($output);
    //printf(str_repeat("*", $max_chars) . "\n");
    exit(1);
}

$api = $api_list[$test_index-1];
$api['header'] = array_merge($base_header, $api['header']);
$api['param'] = array_merge($base_get, $api['param']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_USERAGENT, $base_header['User-Agent']);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
if ($api['method'] == "GET") {
    if (!empty($api['param'])) {
        if (strpos($api['uri'], "?") === false) {
            $api['uri'] .= "?" . http_build_query($api['param']);
        } else {
            $api['uri'] .= "&" . http_build_query($api['param']);
        }
    }
    curl_setopt($ch, CURLOPT_URL, $base_config['BASE_URL'] . $api['uri']);
    if (isset($api['header']['Content-Type'])) {
        unset($api['header']['Content-Type']);
    }
} elseif ($api['method'] == "POST") {
    $post_data = $api['param'];
    if (isset($api['header']['Content-Type']) && preg_match("/multipart\/form\-data/", $api['header']['Content-Type'])) {
        $api['header']['Content-Type'] = "multipart/form-data";
    } else {
        $api['header']['Content-Type'] = "application/x-www-form-urlencoded";
        $post_data = http_build_query($api['param']);
    }
    curl_setopt($ch, CURLOPT_POST, true);
    !empty($post_data) && curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_URL, $base_config['BASE_URL'] . $api['uri']);
}

$tmp = $api['header'];
$api['header'] = [];
foreach ($tmp as $key => $val) {
    array_push($api['header'], "$key: $val");
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $api['header']);

printf("%s %s\n\n", $api['method'], $base_config['BASE_URL'] . $api['uri']);
printf("REQUEST: %s\nRESPONSE: ", json_encode($api, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

$response = curl_exec($ch);

if ($response === false) {
    echo "\ncURL Error: " . curl_error($ch);
    exit(1);
}
curl_close($ch);
try {
    $res = json_encode(json_decode($response, true), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    if ($res == null || $res == "null") {
        throw new Exception();
    } else {
        echo $res;
    }
} catch (Exception $e) {
    echo $response;
}
