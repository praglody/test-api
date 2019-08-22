# api template layout

# 定义全局常量量
# BASE_URL 是必须的
@BASE_URL: http://127.0.0.1

# 全局hader配置
--- header
Cache-Control: no-cache         # 禁用服务器缓存
User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.100 Safari/537.36
Content-Type: application/x-www-form-urlencoded
#Content-Type: multipart/form-data

# 全局GET参数
--- get
token: 1b4197f70b8beda077a70144d1abe982     # 万能toten
output: json
user_id: 90002486

# 定义一个接口
=== Test: /m/user/get 获取用户信息接口

# 定义接口特有的header
--- header
# 每个请求单独的heder定义
# 如果重名，会覆盖全局的header
foo: bar

# 定义请求的数据, 暂时仅支持GET|POST
--- request
GET /m/user/get
output: json
user_id: 90002486


# 定义更多接口
=== Test: /m/user_books/get 获取用户图书的接口

# 定义请求的数据, 暂时仅支持GET|POST
--- request
GET /m/user_books/get
output: json
user_id: 90002486
