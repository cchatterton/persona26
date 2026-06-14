<?php
/**
 * Persona26 Visitor Identity
 *
 * Canonical store: localStorage
 * Cookie: mirrored from localStorage on every page load
 *
 * Note: ID is generated client-side (JS). PHP cookie helpers are not used.
 */

class p26_Cookie {

    // 20 years = practical "forever"
    const FOREVER = 630720000;

    private static $prefix = 'p26_';

    public static function name(string $key): string {
        return self::$prefix . $key;
    }

    /**
     * Output the sync script.
     * Flow:
     * 1) If localStorage has id -> set/overwrite cookie to match
     * 2) Else if cookie has id -> seed localStorage from cookie
     * 3) Else -> generate id -> set both
     */
    public static function outputSyncScript(string $key = 'id'): void {
        $cookie = esc_js(self::name($key));
        $ls     = esc_js(self::name($key));
        $maxAge = (int) self::FOREVER;

        echo "<script>(function(){try{
            var COOKIE='{$cookie}';
            var LSKEY='{$ls}';
            var MAXAGE={$maxAge};

            function escRe(s){ return s.replace(/([.$?*|{}()\\[\\]\\\\\\/\\+^])/g,'\\\\$1'); }

            function getCookie(n){
                var m=document.cookie.match('(^|; )'+escRe(n)+'=([^;]*)');
                return m?decodeURIComponent(m[2]):'';
            }
            function setCookie(n,v){
                var secure=(location.protocol==='https:')?'; Secure':'';
                document.cookie = n+'='+encodeURIComponent(v)+'; Path=/; Max-Age='+MAXAGE+'; SameSite=Lax'+secure;
            }
            function getLS(k){ try{ return localStorage.getItem(k)||'' }catch(e){ return '' } }
            function setLS(k,v){ try{ localStorage.setItem(k,v) }catch(e){} }

            function gen(){
                if (window.crypto && crypto.getRandomValues){
                    var b=new Uint8Array(32); // 64 hex chars
                    crypto.getRandomValues(b);
                    return Array.from(b,function(x){return x.toString(16).padStart(2,'0')}).join('');
                }
                return (Math.random().toString(16).slice(2)+Math.random().toString(16).slice(2)).slice(0,32);
            }

            var ls = getLS(LSKEY);
            var ck = getCookie(COOKIE);

            if (ls) {
                if (ck !== ls) setCookie(COOKIE, ls);
                return;
            }

            if (ck) {
                setLS(LSKEY, ck);
                return;
            }

            var id = gen();
            setLS(LSKEY, id);
            setCookie(COOKIE, id);

        }catch(e){} })();</script>\n";
    }
}

function p26_output_identity_sync_script(): void {
    p26_Cookie::outputSyncScript('id');
}
add_action('wp_head', 'p26_output_identity_sync_script', 1);
