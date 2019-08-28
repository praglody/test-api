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
token: 1b4197f70b8beda077a70144d1abe982
output: json
user_id: 90002486

# 全局POST参数，仅适用POST接口
--- post
token: 1b4197f70b8beda077a70144d1abe982

# 全局cookie
--- cookie
token: 1b4197f70b8beda077a70144d1abe982

# 定义一个接口，`===` 是一个接口的起始标志符
=== Test: /m/user/get 获取用户信息接口

# 定义接口特有的header，如果重名，会覆盖全局的header
# `--- header`是定义header的标识符
--- header
foo: bar

# 定义请求的数据, 暂时仅支持GET|POST
--- request
GET /m/user/get
# 请求标识行的下一行就是GET参数，可以不写或直接将get参数拼到url后面
output: json
user_id: 90002486

# 定义接口的POST参数，仅post请求生效
--- post
user_name: praglody



# 定义更多接口
=== Test: /m/user_books/get 获取用户图书的接口

# 定义请求的数据, 暂时仅支持GET|POST
--- request
GET /m/user_books/get
output: json
user_id: 90002486
