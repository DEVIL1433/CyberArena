<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CyberArena</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&display=swap');
  :root{
    --bg:#0a0f0d; --panel:#0f1613; --panel-2:#131c19; --line:#1f2b26;
    --green:#3ddc84; --green-dim:#26906a; --cyan:#4fd1ff; --amber:#e8a33d;
    --red:#ff5c5c; --text:#cfe9de; --muted:#5c7269; --white:#eafff4;
  }
  *{box-sizing:border-box;}
  html,body{height:100%;}
  body{margin:0;background:radial-gradient(1200px 600px at 15% -10%, rgba(61,220,132,0.05), transparent 60%),var(--bg);color:var(--text);font-family:'IBM Plex Mono',ui-monospace,monospace;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
  .frame{width:100%;max-width:1040px;height:88vh;max-height:840px;background:var(--panel);border:1px solid var(--line);border-radius:10px;box-shadow:0 0 0 1px rgba(0,0,0,0.4),0 30px 80px rgba(0,0,0,0.55),inset 0 0 60px rgba(61,220,132,0.02);display:flex;flex-direction:column;overflow:hidden;}
  .titlebar{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:linear-gradient(180deg,#121a17,#0f1613);border-bottom:1px solid var(--line);flex-shrink:0;}
  .titlebar .dots{display:flex;gap:6px;}
  .dots span{width:10px;height:10px;border-radius:50%;background:#233129;display:inline-block;}
  .titlebar .name{font-size:12px;letter-spacing:.12em;color:var(--muted);text-transform:uppercase;}
  .titlebar .name b{color:var(--green);}
  .status{display:grid;grid-template-columns:1fr auto auto;gap:14px;padding:10px 16px;background:var(--panel-2);border-bottom:1px solid var(--line);font-size:12px;flex-shrink:0;}
  .status .obj{color:var(--text);}
  .status .obj .label{color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-size:10px;display:block;margin-bottom:2px;}
  .status .rank{color:var(--cyan);white-space:nowrap;}
  .trace-wrap{width:150px;}
  .trace-wrap .label{color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-size:10px;display:flex;justify-content:space-between;}
  .trace-bar{height:6px;border-radius:3px;background:#1a2420;overflow:hidden;margin-top:4px;border:1px solid var(--line);}
  .trace-fill{height:100%;width:0%;background:linear-gradient(90deg,var(--amber),var(--red));transition:width .4s ease;}
  .screen{flex:1;overflow-y:auto;padding:16px 18px;font-size:13.5px;line-height:1.65;}
  .screen::-webkit-scrollbar{width:10px;}
  .screen::-webkit-scrollbar-thumb{background:#1c2622;border-radius:6px;}
  .line{white-space:pre-wrap;word-break:break-word;}
  .line.cmd{color:var(--white);}
  .line.cmd .prompt{color:var(--green);}
  .out-ok{color:var(--green);} .out-info{color:var(--text);} .out-muted{color:var(--muted);}
  .out-warn{color:var(--amber);} .out-error{color:var(--red);} .out-cyan{color:var(--cyan);}
  .flag{color:#0a0f0d;background:var(--green);padding:1px 6px;border-radius:3px;font-weight:600;}
  table.nmap{border-collapse:collapse;margin:4px 0;} table.nmap td{padding:1px 14px 1px 0;}
  .dirname{color:var(--cyan);} .filename{color:var(--text);}
  .lab{margin:10px 0;border:1px solid var(--line);background:#0c1310;border-radius:6px;padding:14px 16px;max-width:420px;}
  .lab .lab-title{color:var(--cyan);font-size:11px;letter-spacing:.1em;text-transform:uppercase;margin-bottom:10px;}
  .lab label{display:block;font-size:11px;color:var(--muted);margin:8px 0 4px;text-transform:uppercase;letter-spacing:.06em;}
  .lab input{width:100%;background:#0a0f0d;border:1px solid var(--line);color:var(--white);font-family:inherit;font-size:13px;padding:7px 9px;border-radius:4px;}
  .lab input:focus{outline:none;border-color:var(--green-dim);}
  .lab button{margin-top:12px;background:var(--green);color:#06110b;border:none;font-family:inherit;font-weight:600;font-size:12px;letter-spacing:.06em;text-transform:uppercase;padding:8px 14px;border-radius:4px;cursor:pointer;}
  .lab button:hover{background:#57e79a;}
  .inputRow{display:flex;align-items:center;gap:8px;padding:12px 18px;border-top:1px solid var(--line);background:var(--panel-2);flex-shrink:0;}
  .inputRow .prompt{color:var(--green);font-size:13.5px;white-space:nowrap;}
  .inputRow input{flex:1;background:transparent;border:none;color:var(--white);font-family:inherit;font-size:13.5px;caret-color:var(--green);}
  .inputRow input:focus{outline:none;}
  .inputRow input.masked{-webkit-text-security:disc;text-security:disc;}
  @media (max-width:640px){.frame{height:94vh;} .status{grid-template-columns:1fr;gap:6px;} .trace-wrap{width:100%;}}
</style>
</head>
<body>

<div class="frame">
  <div class="titlebar">
    <div class="dots"><span></span><span></span><span></span></div>
    <div class="name">CYBERARENA // <b>secure shell</b></div>
    <div class="name" id="clock">--:--:--</div>
  </div>
  <div class="status">
    <div class="obj"><span class="label">Current objective</span><span id="objText">Connecting...</span></div>
    <div class="rank"><span id="rankText">RANK: RECRUIT</span></div>
    <div class="trace-wrap">
      <div class="label"><span>trace</span><span id="traceNum">0%</span></div>
      <div class="trace-bar"><div class="trace-fill" id="traceFill"></div></div>
    </div>
  </div>
  <div class="screen" id="screen"></div>
  <div class="inputRow">
    <span class="prompt" id="promptLabel">recruit@cyberarena:~$</span>
    <input id="cmdInput" autocomplete="off" spellcheck="false" autofocus />
  </div>
</div>

<script>
(function(){
  const screen = document.getElementById('screen');
  const input = document.getElementById('cmdInput');
  const promptLabel = document.getElementById('promptLabel');
  const objText = document.getElementById('objText');
  const rankText = document.getElementById('rankText');
  const traceFill = document.getElementById('traceFill');
  const traceNum = document.getElementById('traceNum');
  let history = [], histIdx = -1;

  function esc(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function line(html, cls){
    const d = document.createElement('div');
    d.className = 'line ' + (cls||'out-info');
    d.innerHTML = html;
    screen.appendChild(d);
    screen.scrollTop = screen.scrollHeight;
  }
  function applyStatus(s){
    objText.textContent = s.objective;
    rankText.textContent = s.rank;
    traceFill.style.width = s.trace + '%';
    traceNum.textContent = s.trace + '%';
    promptLabel.textContent = s.prompt;
    input.classList.toggle('masked', !!s.masked);
  }
  function renderLoginLab(host){
    const wrap = document.createElement('div');
    wrap.className = 'line';
    wrap.innerHTML = `
      <div class="lab">
        <div class="lab-title">ORCHARD // Staff Login Portal</div>
        <label>Username</label><input type="text" id="labUser" placeholder="username" />
        <label>Password</label><input type="password" id="labPass" placeholder="password" />
        <button id="labSubmit">Sign in</button>
      </div>`;
    screen.appendChild(wrap);
    screen.scrollTop = screen.scrollHeight;
    document.getElementById('labSubmit').onclick = () => {
      const u = document.getElementById('labUser').value;
      const p = document.getElementById('labPass').value;
      call({action:'sqli', host, user:u, pass:p});
    };
  }

  async function call(params){
    const body = new URLSearchParams(params);
    const res = await fetch('command.php', { method:'POST', body });
    const data = await res.json();
    if(data.cleared){ screen.innerHTML = ''; }
    (data.lines||[]).forEach(l => line(l.html, l.cls));
    if(data.widget) renderLoginLab(data.widget);
    if(data.status) applyStatus(data.status);
    return data;
  }

  function echoCmd(text){
    line(`<span class="prompt">${esc(promptLabel.textContent)}</span> ${esc(text)}`, 'cmd');
  }

  async function runCommand(raw){
    const text = raw.trim();
    if(text.length === 0) return;
    const masked = input.classList.contains('masked');
    echoCmd(masked ? '•'.repeat(text.length) : text);
    if(text.toLowerCase() === 'clear' && !masked){ screen.innerHTML=''; await call({action:'cmd', cmd:text}); return; }
    await call({action:'cmd', cmd:text});
  }

  input.addEventListener('keydown', (e) => {
    if(e.key === 'Enter'){
      const val = input.value;
      input.value = '';
      if(!input.classList.contains('masked') && val.trim().length){
        history.push(val); histIdx = history.length;
      }
      runCommand(val);
    } else if(e.key === 'ArrowUp'){
      if(history.length){ histIdx = Math.max(0, histIdx-1); input.value = history[histIdx]||''; }
      e.preventDefault();
    } else if(e.key === 'ArrowDown'){
      if(history.length){ histIdx = Math.min(history.length, histIdx+1); input.value = history[histIdx]||''; }
      e.preventDefault();
    }
  });
  document.addEventListener('click', () => input.focus());

  function tickClock(){ document.getElementById('clock').textContent = new Date().toLocaleTimeString(); }
  setInterval(tickClock, 1000);
  tickClock();

  call({action:'boot'});
  input.focus();
})();
</script>
</body>
</html>
