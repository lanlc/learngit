##### 目的
- 降低代码耦合程度，提高项目的可维护性

##### 相关概念
- 依赖倒置原则
	1. 上层定义接口，下层实现接口，下层依赖
	
- 控制反转
	1. 将类(上层)所依赖的单元(下层)的实例化过程交由第三方来实现
	
- 依赖注入
	1. 把类所依赖的单元的实例化过程，放到类的外面去实现
	
- 控制反转容器
	1. 提供了动态地创建、注入依赖单元，映射依赖关系等功能，减少许多代码量
	
- 服务定位器
	1. 把可能用到的依赖单元交由Service Locator进行实例化和创建、配置
	2. 把类对依赖单元的依赖，转变为类对服务定位器的依赖
	
- 依赖注入
	1. 构造函数注入通过构造函数的形参，为类内部的抽象单元提供实例化。
	2. 具体的构造函数调用代码，由外部代码决定。
	3. 与构造函数注入类似，属性注入通过setter或public成员变量，将所依赖的单元注入到类内部。
	4. 具体的属性写入，由外部代码决定

- 要解除依赖的类内部就是内，实例化所依赖单元的地方就是外。

- DI容器知道如何对对象及对象的所有依赖，和这些依赖的依赖，进行实例化和配置。

- 要使用ID容器，先告诉容器、类型与类型之间的依赖关系，也就是所谓的 注册依赖
- 对象的实例化
	1. 解析依赖信息
	2. 实例的创建
	
	
- DI容器解析依赖实例化对象过程大体上是这么一个流程：

	1. 以传入的 $class 看看容器中是否已经有实例化好的单例，如有，直接返回这一单例。
	2. 如果这个 $class 根本就未定义依赖，则调用 build() 创建之。
	3. 对于已经定义了这个依赖，如果定义为PHP callable，则解析依赖关系，并调用这个PHP callable。
	4. 具体依赖关系解析过程等下再说。
	5. 如果依赖的定义是一个数组，首先取得定义中对于这个依赖的 class 的定义。
	6. 然后将定义中定义好的参数数组和配置数组与传入的参数数组和配置数组进行合并，
	7. 并判断是否达到终止递归的条件。从而选择继续递归解析依赖单元，或者直接创建依赖单元。