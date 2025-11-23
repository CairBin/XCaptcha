## [1.3.1] - 2025-11-23

### Fixed

* 修复Altcha PHP72的兼容性问题，PHP72没有`JSON_THROW_ON_ERROR`(PHP73引入)，会导致`./lib/class.altcha.php`报错
* 修复登陆界面验证码占位符不消失以及按钮无法启用的bug(issue #8)
* 修复评论区验证码占位符不消失的bug
* 优化Altcha样式

## [1.3.0] - 2025-10-10

### Fixed

* 修复了Altcha未正确返回值导致评论异常问题
* 修复了PHP8.2不支持动态创建属性而报错(./lib/class.geetestlib.php文件)，前端请求Action接口获取到的是报错信息而非JSON文本，最终导致JSON解析出错Geetest验证码渲染失败的问题。

## [v1.3.0 pre-release]

### Added

* 添加了渲染组件以及校验成功的回调函数，用于禁用/启用按钮和提示验证码组件正在加载

## [v1.2.1]

### Fixed

* 移除./includes/XCaptcha_Config.php的personalConfig方法
* 修复Typecho控制台 -> 个人配置 显示XCaptcha配置找不到的bug(issue #1 )

## [v1.2.0]

### Added

* 添加了altcha，纯本地的工程量计算的验证码方案

## [v1.1.1]

### Deprecated

* 极验证加载不再依赖JQuery，启用JQuery与JQuery CDN URL项已被移除

### Changed

* 对代码进行重构，做了结构性优化

### Added

* 添加管理员评论无需验证功能，此项为可选项
* 兼容Typecho 1.1旧版本

## [v1.0.2]

### Changed

* 修改部分可能引发歧义的字段名称

### Added

* 支持修改验证接口地址
* 添加"极验证展现方式"设置项
* 验证码尺寸样式选项支持极验证

## [v1.0.1]

### Fixed

* 修复了极验证jQuery CDN失效的Bug
* 原默认使用字节跳动的CDN加载jQuery，部分地区可能加载失效，现在默认采用jsDelivr

### Added

* 支持在配置页面启用或禁用插件自带的jQuery，对于部分自带jQuery的主题（如Handsome）无需启用该项
* 为了避免资源失效，支持在配置页修改jQuery CDN地址，此项仅在勾选启用插件自带的jQuery选项后生效

### Others

* 本插件的jQuery仅用于极验证，对其它验证码的使用无影响。
