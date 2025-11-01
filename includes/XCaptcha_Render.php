<?php

include_once "XCaptcha_Utils.php";

class XCaptcha_Render
{
    /**
     * 输出通用部分HTML和JS
     * @static
     * @param string  $cdnUrl        验证码渲染脚本CDN地址
     * @param string  $captchaScript 插入的JS脚本
     * @param string  $parg          哪个页面
     * @return {*}
     */    
    private static function echoContent($cdnUrl, $captchaScript, $page, $captchaChoosen)
    {
        echo <<<EOF
        <style rel="stylesheet">
        .precheck-label { line-height: 44px;text-align:center;background-color: #e8e8e8; color: #4d4d4d; }
        </style>
        <script>
            window.beforeCheckCallback = () => {
                console.log("XCaptcha: 正在加载验证码样式...");
                setTimeout(() => {
                    const checkLabel = document.getElementsByClassName('check-label');
                    if (checkLabel.length > 0) {
                        precheckLabel = document.getElementsByClassName('precheck-label');
                        for(var i=0; i<precheckLabel.length; i++){
                            precheckLabel[i].style.display = 'none';
                        }
                    }
                }, 50);
                console.log("XCaptcha: 验证码样式加载完毕!");
            }
            

            window.addEventListener('load', ()=>{
                const buttons = document.querySelectorAll('.custom-submit-button');
                buttons.forEach(btn => {
                    btn.disabled = true;
                });
            });
        </script>
EOF;

        if($page == 'login'){
            echo <<<EOF
		    <script>
			    window.checkCallback = () => {
                    var jqForm = $("form");
                    var jqFormSubmit = jqForm.find(":submit");
                    jqFormSubmit.prop('disabled', false);
                };
                $(document).ready(function () {
                    var jqForm = $("form");
                    var jqFormSubmit = jqForm.find(":submit");
                    jqFormSubmit.prop('disabled', true);
                    jqFormSubmit.parent().before(`$captchaScript`);
                    jqFormSubmit.parent().before(`<div class="precheck-label"><p class="waiting">行为验证™ 安全组件加载中...</p></div>`);
                    
                });
		    </script>
        
EOF;
        }else if($page == 'comments'){
            echo <<<EOF
            <script>
            window.checkCallback = ()=>{
                console.log("XCaptcha: Success to verify passcode.")
                var btns = document.getElementsByClassName('custom-submit-button');
                for(var i=0; i<btns.length; i++){
                    btns[i].disabled = false;
                }
            }
            </script>
EOF;
            echo $captchaScript;
            echo '<div class="precheck-label">行为验证™ 安全组件加载中...</div>';

        }   
        if($captchaChoosen == 'altcha'){
            echo<<<EOF
            <script src="$cdnUrl" type="module"></script>
EOF;
        }else{
                echo<<<EOF
        <script src="$cdnUrl"></script>
EOF;
        }
    }

    /**
     * 输出Geetest渲染的HTML JS
     * @param string $widgetSize 尺寸
     * @param XCaptcha_GeetestInfo $geetest 极验证信息类
     */    
    private static function echoGeetestContent($widgetSize, XCaptcha_GeetestInfo $geetest)
    {
        $sizeSelector = ["normal" => "300px", "flexible"=>"100%", "compact" => "150px"];
        $geetestSize = $sizeSelector[$widgetSize];
        $dismod = $geetest->dismod;

        $ajaxUri = '/index.php/action/xcaptcha?do=ajaxResponseCaptchaData&type=geetest';
        echo <<<EOF
        <script>
            console.log("XCaptcha: 正在配置Geetest初始化函数...");
            const initializeGeetestCaptcha = ()=>{
                var ajaxUri = "{$ajaxUri}";
                var url = ajaxUri + '&t=' + (new Date()).getTime();
                
                fetch(url, {method: 'GET'})
               .then(function (response) {
                    if (!response.ok){
                        throw new Error(`Http error!`);
                    }
                    return response.text();
                })
               .then(function (text) {
                    try{
                        const data = JSON.parse(text);
                        initGeetest({
                            gt: data.gt,
                            challenge: data.challenge,
                            new_captcha: data.new_captcha,
                            product: '$dismod',
                            offline:!data.success,
                            width: '$geetestSize'
                        }, function (captchaObj) {
                            console.log("XCaptcha: Geetest请求成功!");
                            var jqGtCaptcha = document.getElementById('gt-captcha');
                            captchaObj.appendTo(jqGtCaptcha);
                            captchaObj.onSuccess(window.checkCallback);
                            window.beforeCheckCallback && window.beforeCheckCallback();
                        });
                    }catch(e){
                        console.error("XCaptcha: JSON解析错误: ", e);
                        console.error("XCaptcha: 导致解析错误的文本: ", text);
                    }
                    
                })
               .catch(function (error) {
                    console.log('请求出错：', error);
                });
            }
            initializeGeetestCaptcha();
            
		</script>

EOF;

    }

    private static function echoAltchaContent(){
        echo<<<EOF
        <script>
            window.beforeCheckCallback && window.beforeCheckCallback();
            if (window.isSecureContext && window.crypto) {
                console.log("Web Crypto API is available and secure context is present.");
            } else {
                console.log("Web Crypto API is not available or secure context is not present.");
            }
            document.querySelector('#altcha').addEventListener('statechange', (ev) => {
                //console.log('state:', ev.detail.state);
                if (ev.detail.state === 'verified') {
                    //console.log('payload:', ev.detail.payload);
                    window.checkCallback && window.checkCallback();
                }
            });
            </script>
EOF;
    }

    /**
     * 渲染登录/注册页的验证码
     * @static
     * @param XCaptcha_Config $config 配置类
     */    
    public static function renderLoginPage(XCaptcha_Config $config)
    {
        $widgetOptions = Typecho_Widget::widget('Widget_Options');
        if (stripos($widgetOptions->request->getRequestUrl(), 'login.php') === false &&
			stripos($widgetOptions->request->getRequestUrl(), 'register.php') === false) {
            return;
        }

        // 是否启用login的验证码
        if(!$config->isCaptchaEnabledOnPage('login')){
            return;
        }
        // 密钥是否为空，如果为空则不渲染验证码
        if(!$config->checkKeys()){
            Typecho_Widget::widget('Widget_Notice')->set(_t('XCaptcha: No keys'), 'error');
			return;
        }

        list($captchaScript, $cdnUrl) = XCaptcha_Utils::getCaptchaScript(
            $config->getCaptchaChoosen(),
            $config->getCdnUrl(),
            $config->getWidgetColor(),
            $config->getwidgetSize(),
            $config->getCaptchaId()
        );
        if (!$captchaScript) return;    // 模板不存在则不渲染

        self::echoContent($cdnUrl, $captchaScript, 'login', $config->getCaptchaChoosen());    // 输出通用部分
        if($config->getCaptchaChoosen() == 'geetest'){
            // geetest需要额外配置
            self::echoGeetestContent($config->getWidgetSize(), $config->getGeetestConfig());
        }
        if($config->getCaptchaChoosen() == 'altcha'){
            self::echoAltchaContent();
        }
    }

    /**
     * 渲染评论区的验证码
     * @static
     * @param XCaptcha_Config $config 配置类
     */  
    public static function renderCommentPage(XCaptcha_Config $config)
    {
		// 是否启用comments的验证码
        if(!$config->isCaptchaEnabledOnPage('comments')){
            return;
        }
        // 密钥是否为空，如果为空则不渲染验证码
        if(!$config->checkKeys()){
            Typecho_Widget::widget('Widget_Notice')->set(_t('XCaptcha: No keys'), 'error');
			return;
        }
        // 是否开启登录用户不校验 且 用户处于登录状态 且 为administrator，都符合则不渲染
        $user = Typecho_Widget::widget('Widget_User');
        if($config->isAuthorUncheck() && $user->hasLogin() && $user->pass('administrator', true))
            return;

        list($captchaScript, $cdnUrl) = XCaptcha_Utils::getCaptchaScript(
            $config->getCaptchaChoosen(),
            $config->getCdnUrl(),
            $config->getWidgetColor(),
            $config->getwidgetSize(),
            $config->getCaptchaId()
        );
        if (!$captchaScript) return;

        self::echoContent($cdnUrl, $captchaScript, 'comments', $config->getCaptchaChoosen());    // 输出通用部分
        if($config->getCaptchaChoosen() == 'geetest'){
            // geetest需要额外配置
            self::echoGeetestContent($config->getWidgetSize(), $config->getGeetestConfig());
        }
        if($config->getCaptchaChoosen() == 'altcha'){
            self::echoAltchaContent();
        }
    }
    
}