redis的编码检查
----------
redis尝试对字符串进行编码转换，以便更好的利用内存空间

> * o->encoding
> * 检查是否是string类型
> * 只在字符串的编码为 RAW 或者 EMBSTR 时尝试进行编码
> * 不对共享对象进行编码
> * 只对长度小于或等于 21 字节，并且可以被解释为整数的字符串进行编码
> * 尝试将 RAW 编码的字符串编码为 EMBSTR 编码
> * 对象没办法进行编码，尝试从 SDS 中移除所有空余空间