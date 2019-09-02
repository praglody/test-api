#!/usr/bin/env php
<?php

define("COMMENT_REGEX", "/^\s*#.*/");                                           // 单行注释正则
define("KEY_VALUE_REGEX", "/^\s*([\w-]*)\s*:\s*(.*?)\s*(#.*?)?\s*$/");          // key value 且带注释的正则
define("GLOBAL_PARAM_REGEX", "/\s*@\s*([\w-]*)\s*:\s*(.*)\s*/");                // 全局变量正则
define("REQUEST_PARAM_REGEX", "/^\s*---\s*(header|get|post|cookie)\s*(#.*?)?\s*$/");   // 请求参数标识的正则

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

$test_content = array_filter(explode("\n", $test_content), function ($line) {
    return !(empty($line) || preg_match(COMMENT_REGEX, $line));
});
$line_numbers = array_map(function ($num) {
    return ++$num;
}, array_keys($test_content));
$lines = array_values($test_content);

$_HEADER = [];
$_POST = [];
$_GET = [];
$_COOKIE = [];
$_CONFIG = [
    "BASE_URL" => "http://127.0.0.1",
];
$i = 0;
// 解析全局配置
while ($i < sizeof($lines)) {
    preg_match(GLOBAL_PARAM_REGEX, $lines[$i], $base_config_match);
    if (!empty($base_config_match)) {
        $_CONFIG[$base_config_match[1]] = trim($base_config_match[2]);
    } else {
        break;
    }
    $i++;
}

// 解析header
for (; $i < sizeof($lines); $i++) {
    if (preg_match(REQUEST_PARAM_REGEX, $lines[$i], $request_param_type_match)) {
        $match_param = [];
        while (++$i < sizeof($lines)) {
            if (preg_match(KEY_VALUE_REGEX, $lines[$i], $match)) {
                $match_param[$match[1]] = trim($match[2]);
            } else {
                $i--;
                break;
            }
        }
        if ($request_param_type_match[1] == "header") {
            $_HEADER += $match_param;
        } elseif ($request_param_type_match[1] == "get") {
            $_GET += $match_param;
        } elseif ($request_param_type_match[1] == "post") {
            $_POST += $match_param;
        } elseif ($request_param_type_match[1] == "cookie") {
            $_COOKIE += $match_param;
        }
    } else {
        break;
    }
}

$api_list = [];

$api_index = 1;
for (; $i < sizeof($lines); $i++) {
    $api = ['header' => []];
    if (preg_match("/^\s*===\s*Test\s*:\s*(.*)/", $lines[$i], $match)) {
        $api['index'] = $api_index++;
        $api['title'] = trim($match[1]);
    } else {
        printf("api格式不正确，错误行数 %d \"%s\"\n", $line_numbers[$i], trim($lines[$i]));
        exit(1);
    }

    for ($i += 1; $i < sizeof($lines); $i++) {
        if (preg_match("/---\s*request/", $lines[$i])) {
            // request 请求定义
            while (++$i < sizeof($lines)) {
                if (preg_match("/(GET|POST|DELETE|PUT)\s*(.*)/", $lines[$i], $match)) {
                    $api['method'] = $match[1];
                    $api['uri'] = trim($match[2]);
                    if (empty($api['method']) || empty($api['uri'])) {
                        printf("api格式不正确\n%s\n", $lines[$i]);
                        exit(1);
                    }
                    break;
                }
            }

            // 获取请求参数
            while (++$i < sizeof($lines)) {
                if (preg_match(KEY_VALUE_REGEX, $lines[$i], $match)) {
                    $api['get'][$match[1]] = trim($match[2]);
                } else {
                    $i--;
                    break;
                }
            }
        } elseif (preg_match(REQUEST_PARAM_REGEX, $lines[$i], $request_param_type_match)) {
            $match_param = [];
            while (++$i < sizeof($lines)) {
                if (preg_match(KEY_VALUE_REGEX, $lines[$i], $match)) {
                    $match_param[$match[1]] = trim($match[2]);
                } else {
                    $i--;
                    break;
                }
            }
            $api[$request_param_type_match[1]] = $match_param;
        } else {
            $i--;
            break;
        }
    }
    $api_list[] = $api;
}

$test_index = $argv[2] ?? 0;

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
    exit(1);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在

$api = $api_list[$test_index - 1];
$api['header'] = array_merge($_HEADER, $api['header'] ?? []);
$api['get'] = array_merge($_GET, $api['get'] ?? []);
$api['post'] = array_merge($_POST, $api['post'] ?? []);
$api['cookie'] = array_merge($_COOKIE, $api['cookie'] ?? []);
if (!empty($api['get'])) {
    if (strpos($api['uri'], "?") === false) {
        $request_url = $api['uri'] . "?" . http_build_query($api['get']);
    } else {
        $request_url = $api['uri'] . "&" . http_build_query($api['get']);
    }
} else {
    $request_url = $api['uri'];
}

if ($api['method'] == "GET") {
    if (isset($api['header']['Content-Type'])) {
        unset($api['header']['Content-Type']);
    }
    unset($api['post']);
} elseif ($api['method'] == "POST") {
    curl_setopt($ch, CURLOPT_POST, true);
    if (!empty($api['post'])) {
        $post_data = $api['post'];
        if (isset($api['header']['Content-Type'])
            && preg_match("/multipart\/form-data/", $api['header']['Content-Type'])) {
            $api['header']['Content-Type'] = "multipart/form-data";
        } else {
            $api['header']['Content-Type'] = "application/x-www-form-urlencoded";
            $post_data = http_build_query($api['post']);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    }
}

$tmp = $api['header'];
$api['header'] = [];
foreach ($tmp as $key => $val) {
    array_push($api['header'], "$key: $val");
}
if (!empty($api['cookie'])) {
    $cookie = [];
    foreach ($api['cookie'] as $key => $val) {
        $cookie[] = sprintf("%s=%s", urlencode($key), urlencode($val));
    }
    array_push($api['header'], sprintf("Cookie: %s", implode("; ", $cookie)));
}

curl_setopt($ch, CURLOPT_URL, $_CONFIG['BASE_URL'] . $request_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $api['header']);

$request = [];
empty($api['header']) || $request['header'] = $api['header'];
empty($api['uri']) || $request['uri'] = $api['method'] . ' ' . $api['uri'];
empty($api['get']) || $request['get'] = $api['get'];
($api['method'] == "POST") && !empty($api['post']) && $request['post'] = $api['post'];

printf("%s %s\n\n", $api['method'], $_CONFIG['BASE_URL'] . $request_url);
printf("REQUEST: %s\nRESPONSE: ",
    json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

$response = curl_exec($ch);

if ($response === false) {
    echo "\ncURL Error: " . curl_error($ch);
    exit(1);
}
curl_close($ch);
try {
    $res = json_encode(
        json_decode($response, true),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($res == null || $res == "null") {
        throw new Exception();
    } else {
        echo $res;
    }
} catch (Exception $e) {
    echo $response;
    exit(1);
}
