(function(){try{
            var COOKIE='p26_id';
            var LSKEY='p26_id';
            var MAXAGE=630720000;

            function escRe(s){ return s.replace(/([.$?*|{}()\[\]\\\/\+^])/g,'\\$1'); }

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

            if (!/^[a-f0-9]{16,64}$/.test(ls)) ls = '';
            if (!/^[a-f0-9]{16,64}$/.test(ck)) ck = '';
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

        }catch(e){} })();
