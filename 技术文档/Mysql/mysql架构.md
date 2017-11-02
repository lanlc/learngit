mysql逻辑架构粗略阐述
-------------

mysql可以视为三层架构：

```flow
st=>start: 客户端
io=>inputoutput: 连接/线程处理器、查询缓存、解析器、优化器
op=>inputoutput: 存储引擎
e=>end
st->io->op
```

### 查询执行路径

- 客户端发送一条查询给服务器
- 服务器先检查查询缓存，如果命中了缓存，立刻返回存储在缓存中的结果，否则进入下一阶段
- 服务器端进行SQL解析、预处理，再由优化器生成对应的执行计划
- Mysql根据优化器生成的执行计划，调用存储引擎的API来执行查询、将结果返回给客户端

### 查询逻辑的执行顺序

```
FROM <left_table>
<join_type>  JOIN <right_table>   ON <join_condition>
WHERE <where_condition>
GROUP BY <group_by_list>
WITH {cube | rollup}
HAVING <having_condition>
SELECT   DISTINCT <top_specification>  <select_list>
ORDER BY <order_by_list>

```

- 解析顺序

    (1).FROM 子句 组装来自不同数据源的数据
    
    (2).WHERE 子句 基于指定的条件对记录进行筛选
    
    (3).GROUP BY 子句 将数据划分为多个分组
    
    (4).使用聚合函数进行计算
    
    (5).使用HAVING子句筛选分组
    
    (6).计算所有的表达式
    
    (7).使用ORDER BY对结果集进行排序
    
- 执行顺序
    1.FROM：对FROM子句中前两个表执行笛卡尔积生成虚拟表vt1
    
    2.ON：对vt1表应用ON筛选器只有满足<join_condition>为真的行才被插入vt2
    
    3.OUTER(join)：如果指定了OUTER JOIN保留表(preserved table)中未找到的行将行作为外部行添加到vt2 生成t3如果from包含两个以上表则对上一个联结生成的结果表和下一个表重复执行步骤和步骤直接结束
    
    4.WHERE：对vt3应用 WHERE 筛选器只有使<where_condition> 为true的行才被插入vt4
    
    5.GROUP BY：按GROUP BY子句中的列列表对vt4中的行分组生成vt5
    
    6.CUBE|ROLLUP：把超组(supergroups)插入vt6 生成vt6
    
    7.HAVING：对vt6应用HAVING筛选器只有使<having_condition> 为true的组才插入vt7
    
    8.SELECT：处理select列表产生vt8
    
    9.DISTINCT：将重复的行从vt8中去除产生vt9
    
    10.ORDER BY：将vt9的行按order by子句中的列列表排序生成一个游标vc10
    
    11.TOP：从vc10的开始处选择指定数量或比例的行生成vt11 并返回调用者

