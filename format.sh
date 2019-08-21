#! /bin/bash -x

# 修复不符合PSR2规范的代码
phpcbf --standard=psr2 testapi.php
