### 在PHP7的版本中，Session类里的```session_set_save_handler```有时候会返回false
### 不建议再代码中指定，而是去修改php.ini里的session配置
```
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?auth=authpwd"

```