<!DOCTYPE html>
<html>
<head>

    <!--
    noVNC example: simple example using default UI
    Copyright (C) 2012 Joel Martin
    Copyright (C) 2013 Samuel Mannehed for Cendio AB
    noVNC is licensed under the MPL 2.0 (see LICENSE.txt)
    This file is licensed under the 2-Clause BSD license (see LICENSE.txt).

    Connect parameters are provided in query string:
        http://example.com/?host=HOST&port=PORT&encrypt=1&true_color=1
    or the fragment:
        http://example.com/#host=HOST&port=PORT&encrypt=1&true_color=1
    -->
    <title>noVNC</title>

    <meta charset="utf-8">

    <!-- Always force latest IE rendering engine (even in intranet) & Chrome Frame
                Remove this if you use the .htaccess -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <!-- Apple iOS Safari settings -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <!-- App Start Icon  -->
    <link rel="apple-touch-startup-image" href="images/screen_320x460.png" />
    <!-- For iOS devices set the icon to use if user bookmarks app on their homescreen -->
    <link rel="apple-touch-icon" href="images/screen_57x57.png">
    <!--
    <link rel="apple-touch-icon-precomposed" href="images/screen_57x57.png" />
    -->


    <!-- Stylesheets -->
    <link rel="stylesheet" href="include/base.css" title="plain">

     <!--
    <script type='text/javascript'
        src='http://getfirebug.com/releases/lite/1.2/firebug-lite-compressed.js'></script>
    -->
        <script src="include/util.js"></script>
</head>

<body style="margin: 0px;">
    <div id="noVNC_screen">
            <div id="noVNC_status_bar" class="noVNC_status_bar" style="margin-top: 0px;">
                <table border=0 width="100%"><tr>
                    <td><div id="noVNC_status" style="position: relative; height: auto;">
                        Loading
                    </div></td>
                    <td width="1%"><div id="noVNC_buttons">
                        <input type=button value="Send CtrlAltDel"
                            id="sendCtrlAltDelButton">
                        <span id="noVNC_xvp_buttons">
                        <input type=button value="Shutdown"
                            id="xvpShutdownButton">
                        <input type=button value="Reboot"
                            id="xvpRebootButton">
                        <input type=button value="Reset"
                            id="xvpResetButton">
                        </span>
                            </div></td>
                </tr></table>
            </div>
            <canvas id="noVNC_canvas" width="640px" height="20px">
                Canvas not supported.
            </canvas>
        </div>

        <script>
        /*jslint white: false */
        /*global window, $, Util, RFB, */
        "use strict";

        // Load supporting scripts
        Util.load_scripts(["webutil.js", "base64.js", "websock.js", "des.js",
                           "keysymdef.js", "keyboard.js", "input.js", "display.js",
                           "inflator.js", "rfb.js", "keysym.js"]);

        var rfb;
        var resizeTimeout;


        function UIresize() {
            if (WebUtil.getConfigVar('resize', false)) {
                var innerW = window.innerWidth;
                var innerH = window.innerHeight;
                var controlbarH = $D('noVNC_status_bar').offsetHeight;
                var padding = 5;
                if (innerW !== undefined && innerH !== undefined)
                    rfb.setDesktopSize(innerW, innerH - controlbarH - padding);
            }
        }
        function FBUComplete(rfb, fbu) {
            UIresize();
            rfb.set_onFBUComplete(function() { });
        }
        function passwordRequired(rfb) {
            var msg;
            msg = '<form onsubmit="return setPassword();"';
            msg += '  style="margin-bottom: 0px">';
            msg += 'Password Required: ';
            msg += '<input type=password size=10 id="password_input" class="noVNC_status">';
            msg += '<\/form>';
            $D('noVNC_status_bar').setAttribute("class", "noVNC_status_warn");
            $D('noVNC_status').innerHTML = msg;
        }
        function setPassword() {
            rfb.sendPassword($D('password_input').value);
            return false;
        }
        function sendCtrlAltDel() {
            rfb.sendCtrlAltDel();
            return false;
        }
        function xvpShutdown() {
            rfb.xvpShutdown();
            return false;
        }
        function xvpReboot() {
            rfb.xvpReboot();
            return false;
        }
        function xvpReset() {
            rfb.xvpReset();
            return false;
        }
        function updateState(rfb, state, oldstate, msg) {
            var s, sb, cad, level;
            s = $D('noVNC_status');
            sb = $D('noVNC_status_bar');
            cad = $D('sendCtrlAltDelButton');
            switch (state) {
                case 'failed':       level = "error";  break;
                case 'fatal':        level = "error";  break;
                case 'normal':       level = "normal"; break;
                case 'disconnected': level = "normal"; break;
                case 'loaded':       level = "normal"; break;
                default:             level = "warn";   break;
            }

            if (state === "normal") {
                cad.disabled = false;
            } else {
                cad.disabled = true;
                xvpInit(0);
            }

            if (typeof(msg) !== 'undefined') {
                sb.setAttribute("class", "noVNC_status_" + level);
                s.innerHTML = msg;
            }
        }

        window.onresize = function () {
            // When the window has been resized, wait until the size remains
            // the same for 0.5 seconds before sending the request for changing
            // the resolution of the session
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function(){
                UIresize();
            }, 500);
        };

        function xvpInit(ver) {
            var xvpbuttons;
            xvpbuttons = $D('noVNC_xvp_buttons');
            if (ver >= 1) {
                xvpbuttons.style.display = 'inline';
            } else {
                xvpbuttons.style.display = 'none';
            }
        }

        window.onscriptsload = function () {
            var host, port, password, path, token;

            $D('sendCtrlAltDelButton').style.display = "inline";
            $D('sendCtrlAltDelButton').onclick = sendCtrlAltDel;
            $D('xvpShutdownButton').onclick = xvpShutdown;
            $D('xvpRebootButton').onclick = xvpReboot;
            $D('xvpResetButton').onclick = xvpReset;

            WebUtil.init_logging(WebUtil.getConfigVar('logging', 'warn'));
            document.title = unescape(WebUtil.getConfigVar('title', 'noVNC'));
            // By default, use the host and port of server that served this file
            host = WebUtil.getConfigVar('host', '<?php echo $_GET['host']; ?>');
            port = WebUtil.getConfigVar('port', '<?php echo $_GET['port']; ?>');

            // if port == 80 (or 443) then it won't be present and should be
            // set manually
            if (!port) {
                if (window.location.protocol.substring(0,5) == 'https') {
                    port = 443;
                }
                else if (window.location.protocol.substring(0,4) == 'http') {
                    port = 80;
                }
            }

            password = WebUtil.getConfigVar('password', '<?php echo $_GET['ticket']; ?>');
            path = WebUtil.getConfigVar('path', '<?php echo $_GET['path']; ?>');

            // If a token variable is passed in, set the parameter in a cookie.
            // This is used by nova-novncproxy.
            token = WebUtil.getConfigVar('token', null);
            if (token) {

                // if token is already present in the path we should use it
                path = WebUtil.injectParamIfMissing(path, "token", token);

                WebUtil.createCookie('token', token, 1)
            }

            if ((!host) || (!port)) {
                updateState(null, 'fatal', null, 'Must specify host and port in URL');
                return;
            }

            try {
                rfb = new RFB({'target':       $D('noVNC_canvas'),
                               'encrypt':      WebUtil.getConfigVar('encrypt',true),
                               'repeaterID':   WebUtil.getConfigVar('repeaterID', ''),
                               'true_color':   WebUtil.getConfigVar('true_color', true),
                               'local_cursor': WebUtil.getConfigVar('cursor', true),
                               'shared':       WebUtil.getConfigVar('shared', true),
                               'view_only':    WebUtil.getConfigVar('view_only', false),
                               'onUpdateState':  updateState,
                               'onXvpInit':    xvpInit,
                               'onPasswordRequired':  passwordRequired,
                               'onFBUComplete': FBUComplete});
            } catch (exc) {
                updateState(null, 'fatal', null, 'Unable to create RFB client -- ' + exc);
                return; // don't continue trying to connect
            }

            rfb.connect(host, port, password, path);
        };
        </script>

    </body>
</html>
