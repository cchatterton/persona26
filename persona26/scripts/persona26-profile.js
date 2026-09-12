window.p26ApplyProfileUpdates = function(updates, clearPending){try{
        var PROFILE_KEY='p26_profile';
        var PENDING_KEY='p26_pending_profile';
        var UPDATES=updates;
        var CLEAR_PENDING=clearPending;
        var MAXAGE=630720000;

        function getLS(k){ try{ return localStorage.getItem(k)||''; }catch(e){ return ''; } }
        function setLS(k,v){ try{ localStorage.setItem(k,v); }catch(e){} }
        function getCookie(n){
            var m=document.cookie.match('(?:^|; )'+n.replace(/([.$?*|{}()\[\]\\\/\+^])/g,'\\$1')+'=([^;]*)');
            return m?decodeURIComponent(m[1]):'';
        }
        function setCookie(n,v){
            try{ document.cookie=n+'='+encodeURIComponent(v)+'; Path=/; Max-Age='+MAXAGE+'; SameSite=Lax'+((location.protocol==='https:')?'; Secure':''); }catch(e){}
        }
        function clearCookie(n){
            try{ document.cookie=n+'=; Path=/; Max-Age=0; SameSite=Lax'; }catch(e){}
        }
        function parseJSON(raw,fallback){
            if(!raw) return fallback;
            try{ var v=JSON.parse(raw); return (v&&typeof v==='object')?v:fallback; }catch(e){ return fallback; }
        }
        function isObj(v){ return !!v&&typeof v==='object'&&!Array.isArray(v); }
        function arr(v){ return Array.isArray(v)?v.filter(Boolean):[]; }
        function ensureProfile(v){
            if(!isObj(v)) v={};
            if(typeof v.persona!=='string') v.persona='';
            if(!isObj(v.counters)) v.counters={};
            return v;
        }
        function topAll(map){
            map=isObj(map)?map:{};
            var winners=[], max=-1;
            for(var key in map){
                if(!Object.prototype.hasOwnProperty.call(map,key)) continue;
                var val=parseInt(map[key],10)||0;
                if(val>max){ max=val; winners=[key]; }
                else if(val===max){ winners.push(key); }
            }
            return winners;
        }
        function profileValues(profile){
            if(!profile||typeof profile.persona!=='string'||!profile.persona) return [];
            return profile.persona.split(/\s*,\s*|\s*\|\s*/).map(function(v){ return String(v).trim(); }).filter(Boolean);
        }
        function applyBodyClasses(oldProfile,newProfile){
            if(!document.body) return;
            profileValues(oldProfile).forEach(function(value){ if (/^[^\s]+$/.test(value)) document.body.classList.remove(value); });
            profileValues(newProfile).forEach(function(value){ if (/^[^\s]+$/.test(value)) document.body.classList.add(value); });
        }
        function rebuildPersona(profile,order){
            var parts=[];
            arr(order).forEach(function(dimKey){
                var winners=topAll(profile.counters[dimKey]);
                if(winners.length) parts.push(winners.join(' | '));
            });
            profile.persona=parts.join(', ');
            return profile;
        }
        function merge(profile,updates){
            var order=[];
            arr(updates).forEach(function(update){
                var dimKey=String(update.dimKey||'').trim();
                var value=String(update.value||'').trim();
                var mode=String(update.mode||'increment');
                if(!/^d[0-9]+$/.test(dimKey)||!value||['__proto__','constructor','prototype'].indexOf(value)!==-1) return;

                arr(update.order).forEach(function(item){
                    item=String(item||'').trim();
                    if(item&&order.indexOf(item)===-1) order.push(item);
                });
                if(order.indexOf(dimKey)===-1) order.push(dimKey);

                if(!isObj(profile.counters[dimKey])) profile.counters[dimKey]={};
                if(mode==='replace'){
                    profile.counters[dimKey]={};
                    profile.counters[dimKey][value]=1;
                } else {
                    profile.counters[dimKey][value]=(parseInt(profile.counters[dimKey][value],10)||0)+1;
                }
            });
            return rebuildPersona(profile,order);
        }

        var raw=getLS(PROFILE_KEY)||getCookie(PROFILE_KEY);
        var oldProfile=ensureProfile(parseJSON(raw,{persona:'',counters:{}}));
        var profile=ensureProfile(parseJSON(JSON.stringify(oldProfile),{persona:'',counters:{}}));
        profile=merge(profile,UPDATES);

        var encoded=JSON.stringify(profile);
        setLS(PROFILE_KEY,encoded);
        setCookie(PROFILE_KEY,encoded);

        window.p26=window.p26||{};
        window.p26.profile=profile;

        function run(){ applyBodyClasses(oldProfile,profile); }
        if(document.body){ run(); } else { document.addEventListener('DOMContentLoaded',run,{once:true}); }

        if(CLEAR_PENDING) clearCookie(PENDING_KEY);
    }catch(e){}};
(function() {
    var action = window.p26ProfileAction || '';
    if (action !== 'clear' && action !== 'get') {
(function(){try{
            var PROFILE_KEY = 'p26_profile';
            var MAXAGE = 630720000;
            var PAGE = window.p26PageProfileData || {order:[],labels:{},dimensions:{}};

            function getLS(k){
                try { return localStorage.getItem(k) || ''; } catch(e){ return ''; }
            }

            function setLS(k,v){
                try { localStorage.setItem(k,v); } catch(e){}
            }

            function getCookie(n){
                var m = document.cookie.match('(?:^|; )' + n.replace(/([.$?*|{}()\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)');
                return m ? decodeURIComponent(m[1]) : '';
            }

            function setCookie(n,v){
                try {
                    document.cookie = n + '=' + encodeURIComponent(v) + '; Path=/; Max-Age=' + MAXAGE + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
                } catch(e){}
            }

            function parseJSON(raw, fallback){
                if (!raw) return fallback;
                try {
                    var v = JSON.parse(raw);
                    return (v && typeof v === 'object') ? v : fallback;
                } catch(e){
                    return fallback;
                }
            }

            function isObj(v){
                return !!v && typeof v === 'object' && !Array.isArray(v);
            }

            function arr(v){
                return Array.isArray(v) ? v.filter(Boolean) : [];
            }

            function ensureProfile(v){
                if (!isObj(v)) v = {};
                if (typeof v.persona !== 'string') v.persona = '';
                if (!isObj(v.counters)) v.counters = {};
                return v;
            }

            function bump(map, values){
                map = isObj(map) ? map : {};
                values.forEach(function(value){
                    value = String(value).trim();
                    if (!value || ['__proto__', 'constructor', 'prototype'].indexOf(value) !== -1) return;
                    map[value] = (parseInt(map[value], 10) || 0) + 1;
                });
                return map;
            }

            function topAll(map){
                map = isObj(map) ? map : {};
                var winners = [];
                var max = -1;

                for (var key in map) {
                    if (!Object.prototype.hasOwnProperty.call(map, key)) continue;
                    var val = parseInt(map[key], 10) || 0;

                    if (val > max) {
                        max = val;
                        winners = [key];
                    } else if (val === max) {
                        winners.push(key);
                    }
                }

                return winners;
            }

            function rebuildPersona(profile, order){
                var parts = [];

                arr(order).forEach(function(dimKey){
                    var winners = topAll(profile.counters[dimKey]);
                    if (winners.length) {
                        parts.push(winners.join(' | '));
                    }
                });

                profile.persona = parts.join(', ');
                return profile;
            }

            function personaValues(profile){
                if (!profile || typeof profile.persona !== 'string' || !profile.persona) return [];
                return profile.persona
                    .split(/\s*,\s*|\s*\|\s*/)
                    .map(function(v){ return String(v).trim(); })
                    .filter(Boolean);
            }

            function applyBodyClasses(oldProfile, newProfile){
                if (!document.body) return;

                personaValues(oldProfile).forEach(function(value){
                    if (/^[^\s]+$/.test(value)) document.body.classList.remove(value);
                });

                personaValues(newProfile).forEach(function(value){
                    if (/^[^\s]+$/.test(value)) document.body.classList.add(value);
                });
            }

            var raw = getLS(PROFILE_KEY) || getCookie(PROFILE_KEY);
            var oldProfile = ensureProfile(parseJSON(raw, {persona:'', counters:{}}));

            // clone-ish base so oldProfile remains available for class cleanup
            var profile = ensureProfile(parseJSON(JSON.stringify(oldProfile), {persona:'', counters:{}}));

            var order = arr(PAGE.order);
            if (!order.length) return;
            var dimensions = isObj(PAGE.dimensions) ? PAGE.dimensions : {};

            order.forEach(function(dimKey){
                var values = arr(dimensions[dimKey]);
                if (!values.length) return;
                profile.counters[dimKey] = bump(profile.counters[dimKey], values);
            });

            profile = rebuildPersona(profile, order);

            var encoded = JSON.stringify(profile);
            setLS(PROFILE_KEY, encoded);
            setCookie(PROFILE_KEY, encoded);

            window.p26 = window.p26 || {};
            window.p26.profile = profile;
            window.p26.page = PAGE;

            function runApplyBodyClasses(){
                applyBodyClasses(oldProfile, profile);
            }
            
            if (document.body) {
                runApplyBodyClasses();
            } else {
                document.addEventListener('DOMContentLoaded', runApplyBodyClasses, { once: true });
            }

        }catch(e){}})();
    }
    document.addEventListener('DOMContentLoaded', function() {
        if (action === 'clear') {
(function(){
            try{
                var KEY = "p26_profile";

                try { localStorage.removeItem(KEY); } catch(e){}

                try {
                    document.cookie = KEY + "=; Path=/; Max-Age=0";
                    document.cookie = KEY + "=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT";
                } catch(e){}

                if (window.p26 && window.p26.profile) {
                    delete window.p26.profile;
                }

                var msg = document.createElement("div");
                msg.className = "p26-clear-0";
                msg.textContent = "Persona cleared. Reloading...";
                msg.setAttribute('role', 'status');
                document.body.appendChild(msg);

                var url = new URL(window.location.href);
                url.searchParams.delete('persona');
                setTimeout(function(){
                    window.location.replace(url);
                }, 300);

            }catch(e){}
        })();
        } else if (action === 'show' || action === 'get') {
(function(){
            try{
                var raw = localStorage.getItem("p26_profile") || "";
                var page = window.p26PageProfileData || null;
                var prettyProfile = raw;
                var prettyPage = page
                    ? JSON.stringify(page, null, 2)
                    : "No current page targets found.";

                try {
                    prettyProfile = raw
                        ? JSON.stringify(JSON.parse(raw), null, 2)
                        : "No p26_profile found in localStorage.";
                } catch(e) {
                    prettyProfile = raw || "No p26_profile found in localStorage.";
                }

                var wrap = document.createElement("div");
                wrap.className = "p26-debug-0";

                var h1 = document.createElement("div");
                h1.className = "p26-debug-1";
                h1.textContent = "Persona26 Profile";

                var h2 = document.createElement("div");
                h2.className = "p26-debug-2";
                h2.textContent = "Current Page Targets";

                var pre1 = document.createElement("pre");
                pre1.className = "p26-debug-3";
                pre1.textContent = prettyPage;

                var h3 = document.createElement("div");
                h3.className = "p26-debug-4";
                h3.textContent = "Local Profile";

                var pre2 = document.createElement("pre");
                pre2.className = "p26-debug-5";
                pre2.textContent = prettyProfile;

                wrap.appendChild(h1);
                wrap.appendChild(h2);
                wrap.appendChild(pre1);
                wrap.appendChild(h3);
                wrap.appendChild(pre2);

                document.body.appendChild(wrap);
            }catch(e){}
        })();
        }
    });
})();
