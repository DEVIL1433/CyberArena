<?php
// ============================================================
// CyberArena server-side engine.
// All game state (filesystem, credentials, flags) lives here,
// on the server, inside the PHP session — never shipped to the browser.
// The browser only ever receives the small set of output lines
// produced by whatever command was just run.
// ============================================================

function dirNode(){ return ['type'=>'dir','children'=>[]]; }
function fileNode($content='', $perm='-rw-r--r--'){ return ['type'=>'file','content'=>$content,'perm'=>$perm]; }

function buildTree($flat){
    $root = dirNode();
    foreach($flat as $path => $content){
        $parts = array_values(array_filter(explode('/', $path)));
        $node = &$root;
        for($i=0; $i<count($parts)-1; $i++){
            $seg = $parts[$i];
            if(!isset($node['children'][$seg])) $node['children'][$seg] = dirNode();
            $node = &$node['children'][$seg];
        }
        $node['children'][end($parts)] = fileNode($content);
        unset($node);
    }
    return $root;
}

function resolvePath($cwd, $home, $pathStr){
    if($pathStr === '' || $pathStr === null) return $cwd;
    if($pathStr === '~' || strpos($pathStr, '~/') === 0){
        $segs = $home;
        $pathStr = substr($pathStr, 1);
    } else {
        $segs = (strpos($pathStr, '/') === 0) ? [] : $cwd;
    }
    foreach(array_filter(explode('/', $pathStr), fn($p)=>$p!=='') as $p){
        if($p === '.') continue;
        elseif($p === '..') array_pop($segs);
        else $segs[] = $p;
    }
    return $segs;
}

// returns a reference to the node at $segs, or a reference to null if missing
function &getRef(&$root, $segs, $i=0){
    if($i >= count($segs)) return $root;
    if($root['type'] !== 'dir' || !isset($root['children'][$segs[$i]])){
        $null = null;
        return $null;
    }
    return getRef($root['children'][$segs[$i]], $segs, $i+1);
}

function displayPath($segs, $home){
    $abs = '/' . implode('/', $segs);
    $homeAbs = '/' . implode('/', $home);
    if($abs === $homeAbs) return '~';
    if(strpos($abs, $homeAbs.'/') === 0) return '~' . substr($abs, strlen($homeAbs));
    return $abs === '' ? '/' : $abs;
}

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ============================================================
// Static content
// ============================================================
function getLocalFlat(){
    return [
        '/home/recruit/assignment_114.txt' =>
            "CASE #114 — ENGAGEMENT BRIEF\n\n" .
            "Client:        Meridian Holdings\n" .
            "Scope:         10.10.14.2 (single external host)\n" .
            "Authorization: signed, on file with legal\n" .
            "Objective:     identify exposed services and assess overall exposure.\n\n" .
            "Rules of engagement: passive and active scanning permitted against the\n" .
            "scoped host only. Do not modify production data. Document every step.\n\n" .
            "— J. Okafor, Engagement Lead",
        '/home/recruit/assignment_115.txt' =>
            "CASE #115 — ENGAGEMENT BRIEF (PENDING)\n\n" .
            "Scope:  10.10.14.5\n" .
            "Status: authorization pending — hold until case #114 is closed out.\n\n" .
            "— J. Okafor, Engagement Lead",
        '/etc/motd' => "Authorized users only. All activity is logged and monitored."
    ];
}

function getNet(){
    return [
        '10.10.14.2' => [
            'name' => 'BEACHHEAD — corp test server',
            'ports' => [
                ['port'=>22,'svc'=>'ssh','ver'=>'OpenSSH 7.4'],
                ['port'=>80,'svc'=>'http','ver'=>'Apache httpd 2.4.6 ((CentOS))'],
            ],
            'web' => [
                'html' => "<html><body>\n<h1>Welcome to CorpTest Intranet</h1>\n<p>Staging environment — do not use production data.</p>\n<!-- TODO: change default cred admin:admin123 before prod -->\n</body></html>"
            ],
            'ssh' => [
                'validUser' => 'admin',
                'validPass' => 'admin123',
                'flat' => [
                    '/home/admin/notes.txt' => "Reminder: rotate credentials weekly.\n- IT Dept",
                    '/home/admin/flag.txt' => 'FLAG{recon_plus_default_creds}',
                    '/var/log/auth.log' =>
                        "Aug 07 09:12:01 beachhead sshd[1092]: Failed password for admin from 203.0.113.4 port 51122 ssh2\n" .
                        "Aug 07 09:12:05 beachhead sshd[1092]: Accepted password for admin from 203.0.113.4 port 51122 ssh2\n" .
                        "Aug 07 09:14:44 beachhead sudo: admin : COMMAND=/bin/true",
                    '/etc/hostname' => 'beachhead',
                ]
            ]
        ],
        '10.10.14.5' => [
            'name' => 'ORCHARD — login gateway',
            'ports' => [ ['port'=>80,'svc'=>'http','ver'=>'nginx 1.18.0 (login portal)'] ],
            'sqli' => [ 'flag' => 'FLAG{classic_or_1equals1_bypass}' ]
        ]
    ];
}

function getMissions(){
    return [
        ['title'=>'Case #114', 'brief'=>'A new engagement has come in. Check your home directory for the brief and take it from there.'],
        ['title'=>'Case #114 — In Progress', 'brief'=>'Continue working the scope you were given.'],
        ['title'=>'Case #114 — In Progress', 'brief'=>'Continue working the scope you were given.'],
        ['title'=>'Case #115', 'brief'=>'Case #114 is closed. A second brief in your home directory is now cleared to proceed.'],
        ['title'=>'Cases Closed', 'brief'=>'Both engagements are complete. Type `debrief` for a summary, or keep working.'],
    ];
}
function getRanks(){ return ['RECRUIT','JUNIOR PENTESTER','PENTESTER','SENIOR OPERATOR','SENIOR OPERATOR']; }

// ============================================================
// Session lifecycle
// ============================================================
function initGame(){
    return [
        'contexts' => [[
            'kind'=>'local','user'=>'recruit','host'=>'cyberarena',
            'fsRoot'=>buildTree(getLocalFlat()),
            'home'=>['home','recruit'], 'cwd'=>['home','recruit']
        ]],
        'stage'=>0, 'trace'=>0, 'scanned'=>[], 'knownCreds'=>[],
        'awaitingPassword'=>null, 'history'=>[],
    ];
}

function &curCtx(&$g){ $i = count($g['contexts'])-1; return $g['contexts'][$i]; }

function statusPayload(&$g){
    $missions = getMissions();
    $ranks = getRanks();
    $stage = $g['stage'];
    $ctx = curCtx($g);
    return [
        'objective' => '['.$missions[$stage]['title'].'] '.$missions[$stage]['brief'],
        'rank' => 'RANK: '.$ranks[min($stage, count($ranks)-1)],
        'trace' => $g['trace'],
        'prompt' => $g['awaitingPassword']
            ? 'Password for '.$g['awaitingPassword']['user'].'@'.$g['awaitingPassword']['host'].':'
            : $ctx['user'].'@'.$ctx['host'].':'.displayPath($ctx['cwd'], $ctx['home']).'$',
        'masked' => (bool)$g['awaitingPassword'],
    ];
}

// ============================================================
// Command implementations. Each pushes onto $out (array of [html,cls]).
// ============================================================
function outp(&$out, $html, $cls='out-info'){ $out[] = ['html'=>$html, 'cls'=>$cls]; }

function addTrace(&$g, &$out, $amount, $reason=null){
    $g['trace'] = max(0, min(100, $g['trace'] + $amount));
    if($reason && $amount > 0) outp($out, "[!] trace risk +{$amount}% — ".esc($reason), 'out-warn');
    if($g['trace'] >= 100){
        outp($out, '[!] SESSION TERMINATED — you were detected. (type `reset` to clear trace and drop sessions)', 'out-error');
        $g['contexts'] = [$g['contexts'][0]];
    }
}
function advanceStage(&$g, &$out, $to){
    if($to > $g['stage']){
        $g['stage'] = $to;
        $missions = getMissions();
        outp($out, '');
        outp($out, '════ OBJECTIVE UPDATED: '.esc($missions[$to]['title']).' ════', 'out-cyan');
    }
}

const MAN = [
    'nmap'=>"nmap <host>\n  Scan a target for open ports and running services.",
    'ssh'=>"ssh <user>@<host>\n  Open a remote shell if the port is open and the password is correct.",
    'open'=>"open <host>\n  Fetch whatever is served on that host's web port.",
    'ls'=>"ls [-l] [-a] [path]\n  List directory contents.",
    'cd'=>"cd [path]\n  Change directory. No argument goes home.",
    'pwd'=>"pwd\n  Print the current working directory.",
    'cat'=>"cat <file> [file2 ...]\n  Print file contents.",
    'echo'=>"echo <text> [> file | >> file]\n  Print text, or write/append it to a file.",
    'mkdir'=>"mkdir [-p] <dir>\n  Create a directory.",
    'touch'=>"touch <file>\n  Create an empty file if it does not exist.",
    'rm'=>"rm [-r] <path>\n  Delete a file. -r required for a directory.",
    'cp'=>"cp <src> <dst>\n  Copy a file.",
    'mv'=>"mv <src> <dst>\n  Move or rename a file.",
    'grep'=>"grep [-i] <pattern> <file>\n  Print matching lines.",
    'find'=>"find [path] -name <pattern>\n  Recursively find files by name (supports *).",
    'wc'=>"wc [-l] <file>\n  Count lines, words, characters.",
    'head'=>"head [-n N] <file>\n  Print the first N lines.",
    'tail'=>"tail [-n N] <file>\n  Print the last N lines.",
    'chmod'=>"chmod <mode> <file>\n  Change a file's permission string.",
    'whoami'=>"whoami\n  Print current username.",
    'id'=>"id\n  Print current identity.",
    'uname'=>"uname [-a]\n  Print system information.",
    'date'=>"date\n  Print the current date/time.",
    'ps'=>"ps [aux]\n  List running processes.",
    'sudo'=>"sudo -l\n  List sudo privileges.",
    'history'=>"history\n  Show command history.",
    'exit'=>"exit\n  Close the current ssh session.",
    'clear'=>"clear\n  Clear the screen.",
    'status'=>"status\n  Re-print the current objective.",
    'reset'=>"reset\n  Reset trace meter and close all ssh sessions.",
    'debrief'=>"debrief\n  Summary of the techniques covered.",
];

function cmd_help(&$out){
    outp($out, 'Filesystem & shell:', 'out-cyan');
    $rows = [
        ['ls [-l][-a] [path]','list directory'],['cd [path]','change directory'],['pwd','print working directory'],
        ['cat <file>','print file contents'],['echo <text> [> file]','print or write text'],
        ['mkdir [-p] <dir>','make directory'],['touch <file>','create empty file'],
        ['rm [-r] <path>','delete file/dir'],['cp <src> <dst>','copy file'],['mv <src> <dst>','move/rename file'],
        ['grep [-i] <pat> <file>','search file for text'],['find [path] -name <pat>','search for files by name'],
        ['wc [-l] <file>','count lines/words/chars'],['head/tail [-n N] <file>','show start/end of file'],
        ['chmod <mode> <file>','change permissions'],['whoami / id','current identity'],
        ['uname [-a]','system info'],['date','current date/time'],['ps [aux]','list processes'],
        ['sudo -l','list sudo privileges'],['history','command history'],['clear','clear screen'],
    ];
    foreach($rows as $r) outp($out, '  <span class="out-cyan">'.str_pad($r[0],24).'</span>'.esc($r[1]));
    outp($out, 'Network / mission:', 'out-cyan');
    $rows2 = [
        ['nmap <host>','scan a target'],['open <host>','fetch a web page / portal'],
        ['ssh user@host','remote login'],['exit','close ssh session'],
        ['status','show objective'],['debrief','recap lessons'],['reset','clear trace / sessions'],
        ['man <command>','detailed help'],
    ];
    foreach($rows2 as $r) outp($out, '  <span class="out-cyan">'.str_pad($r[0],24).'</span>'.esc($r[1]));
}
function cmd_man(&$out, $arg){
    if(!$arg){ outp($out, 'usage: man &lt;command&gt;', 'out-warn'); return; }
    if(!isset(MAN[$arg])){ outp($out, "No manual entry for '".esc($arg)."'.", 'out-error'); return; }
    outp($out, '<pre style="margin:0;white-space:pre-wrap">'.esc(MAN[$arg]).'</pre>');
}

function fsError(&$out, $cmd, $path, $msg){ outp($out, esc("$cmd: $path: $msg"), 'out-error'); }

function cmd_pwd(&$out, &$ctx){ outp($out, '/'.implode('/', $ctx['cwd'])); }

function cmd_ls(&$out, &$ctx, $args){
    $flags = implode('', array_filter($args, fn($a)=>str_starts_with($a,'-')));
    $pathArg = null;
    foreach($args as $a) if(!str_starts_with($a,'-')){ $pathArg = $a; break; }
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $pathArg);
    $node = getRef($ctx['fsRoot'], $segs);
    if($node === null){ fsError($out, 'ls', $pathArg ?? '.', 'No such file or directory'); return; }
    if($node['type'] === 'file'){ outp($out, esc($pathArg)); return; }
    $names = array_keys($node['children']); sort($names);
    if(!$names) return;
    if(strpos($flags,'l') !== false){
        foreach($names as $n){
            $child = $node['children'][$n];
            $isDir = $child['type'] === 'dir';
            $perm = $isDir ? 'drwxr-xr-x' : ($child['perm'] ?? '-rw-r--r--');
            $size = $isDir ? 4096 : strlen($child['content'] ?? '');
            $label = $isDir ? '<span class="dirname">'.esc($n).'/</span>' : '<span class="filename">'.esc($n).'</span>';
            outp($out, "$perm  ".str_pad($size,5,' ',STR_PAD_LEFT)."  $label");
        }
    } else {
        $parts = array_map(fn($n) => $node['children'][$n]['type']==='dir' ? '<span class="dirname">'.esc($n).'/</span>' : '<span class="filename">'.esc($n).'</span>', $names);
        outp($out, implode('  ', $parts));
    }
}

function cmd_cd(&$out, &$ctx, $args){
    $target = $args[0] ?? null;
    $segs = $target ? resolvePath($ctx['cwd'], $ctx['home'], $target) : $ctx['home'];
    $node = getRef($ctx['fsRoot'], $segs);
    if($node === null){ fsError($out, 'cd', $target ?? '~', 'No such file or directory'); return; }
    if($node['type'] !== 'dir'){ fsError($out, 'cd', $target, 'Not a directory'); return; }
    $ctx['cwd'] = $segs;
}

function cmd_cat(&$out, &$g, &$ctx, $args){
    if(!$args){ outp($out, 'usage: cat &lt;file&gt;', 'out-warn'); return; }
    foreach($args as $a){
        $segs = resolvePath($ctx['cwd'], $ctx['home'], $a);
        $node = getRef($ctx['fsRoot'], $segs);
        if($node === null){ fsError($out, 'cat', $a, 'No such file or directory'); continue; }
        if($node['type'] === 'dir'){ fsError($out, 'cat', $a, 'Is a directory'); continue; }
        $content = $node['content'];
        if(str_starts_with($content, 'FLAG{')){
            outp($out, '<span class="flag">'.esc($content).'</span>', 'out-ok');
            if($g['stage'] === 2 && $ctx['kind'] === 'ssh' && ($ctx['hostKey'] ?? '') === '10.10.14.2'){
                outp($out, "Recon → info leak → weak creds. That's a full real-world compromise chain.");
                advanceStage($g, $out, 3);
            }
        } else {
            outp($out, esc($content));
        }
    }
}

function cmd_echo(&$out, &$ctx, $rawText){
    $mode = null; $target = null; $text = $rawText;
    $gtgt = strrpos($rawText, '>>');
    $gt = strrpos($rawText, '>');
    if($gtgt !== false){ $mode='append'; $text = trim(substr($rawText,0,$gtgt)); $target = trim(substr($rawText,$gtgt+2)); }
    elseif($gt !== false){ $mode='write'; $text = trim(substr($rawText,0,$gt)); $target = trim(substr($rawText,$gt+1)); }
    $text = preg_replace('/^["\']|["\']$/','',$text);
    if(!$mode){ outp($out, esc($text)); return; }
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $target);
    $parentSegs = array_slice($segs, 0, -1);
    $parent = &getRef($ctx['fsRoot'], $parentSegs);
    if($parent === null || $parent['type'] !== 'dir'){ fsError($out, 'echo', $target, 'No such file or directory'); return; }
    $fname = end($segs);
    $existing = $parent['children'][$fname] ?? null;
    if($existing && $existing['type'] === 'dir'){ fsError($out, 'echo', $target, 'Is a directory'); return; }
    $newContent = ($mode === 'append' && $existing) ? $existing['content']."\n".$text : $text;
    $parent['children'][$fname] = fileNode($newContent);
}

function cmd_mkdir(&$out, &$ctx, $args){
    $pFlag = in_array('-p', $args);
    $target = null;
    foreach($args as $a) if(!str_starts_with($a,'-')){ $target = $a; break; }
    if(!$target){ outp($out, 'usage: mkdir [-p] &lt;dir&gt;', 'out-warn'); return; }
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $target);
    if($pFlag){
        $node = &$ctx['fsRoot'];
        foreach($segs as $s){
            if(!isset($node['children'][$s])) $node['children'][$s] = dirNode();
            $node = &$node['children'][$s];
        }
    } else {
        $parentSegs = array_slice($segs, 0, -1);
        $parent = &getRef($ctx['fsRoot'], $parentSegs);
        if($parent === null){ fsError($out, 'mkdir', $target, 'No such file or directory'); return; }
        $name = end($segs);
        if(isset($parent['children'][$name])){ fsError($out, 'mkdir', $target, 'File exists'); return; }
        $parent['children'][$name] = dirNode();
    }
}

function cmd_touch(&$out, &$ctx, $args){
    $target = $args[0] ?? null;
    if(!$target){ outp($out, 'usage: touch &lt;file&gt;', 'out-warn'); return; }
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $target);
    $parentSegs = array_slice($segs, 0, -1);
    $parent = &getRef($ctx['fsRoot'], $parentSegs);
    if($parent === null){ fsError($out, 'touch', $target, 'No such file or directory'); return; }
    $name = end($segs);
    if(!isset($parent['children'][$name])) $parent['children'][$name] = fileNode('');
}

function cmd_rm(&$out, &$ctx, $args){
    $recursive = in_array('-r',$args) || in_array('-rf',$args);
    $target = null;
    foreach($args as $a) if(!str_starts_with($a,'-')){ $target = $a; break; }
    if(!$target){ outp($out, 'usage: rm [-r] &lt;path&gt;', 'out-warn'); return; }
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $target);
    $parentSegs = array_slice($segs, 0, -1);
    $parent = &getRef($ctx['fsRoot'], $parentSegs);
    $name = end($segs);
    if($parent === null || !isset($parent['children'][$name])){ fsError($out, 'rm', $target, 'No such file or directory'); return; }
    if($parent['children'][$name]['type'] === 'dir' && !$recursive){ fsError($out, 'rm', $target, 'is a directory (use -r)'); return; }
    unset($parent['children'][$name]);
}

function cmd_cp(&$out, &$ctx, $args){
    if(count($args) < 2){ outp($out, 'usage: cp &lt;src&gt; &lt;dst&gt;', 'out-warn'); return; }
    $srcSegs = resolvePath($ctx['cwd'], $ctx['home'], $args[0]);
    $srcNode = getRef($ctx['fsRoot'], $srcSegs);
    if($srcNode === null || $srcNode['type'] !== 'file'){ fsError($out, 'cp', $args[0], 'No such file'); return; }
    $dstSegs = resolvePath($ctx['cwd'], $ctx['home'], $args[1]);
    $parentSegs = array_slice($dstSegs, 0, -1);
    $parent = &getRef($ctx['fsRoot'], $parentSegs);
    if($parent === null){ fsError($out, 'cp', $args[1], 'No such file or directory'); return; }
    $parent['children'][end($dstSegs)] = fileNode($srcNode['content'], $srcNode['perm']);
}

function cmd_mv(&$out, &$ctx, $args){
    if(count($args) < 2){ outp($out, 'usage: mv &lt;src&gt; &lt;dst&gt;', 'out-warn'); return; }
    $srcSegs = resolvePath($ctx['cwd'], $ctx['home'], $args[0]);
    $srcParentSegs = array_slice($srcSegs, 0, -1);
    $srcParent = &getRef($ctx['fsRoot'], $srcParentSegs);
    $srcName = end($srcSegs);
    if($srcParent === null || !isset($srcParent['children'][$srcName])){ fsError($out, 'mv', $args[0], 'No such file or directory'); return; }
    $dstSegs = resolvePath($ctx['cwd'], $ctx['home'], $args[1]);
    $dstParentSegs = array_slice($dstSegs, 0, -1);
    $dstParent = &getRef($ctx['fsRoot'], $dstParentSegs);
    if($dstParent === null){ fsError($out, 'mv', $args[1], 'No such file or directory'); return; }
    $dstParent['children'][end($dstSegs)] = $srcParent['children'][$srcName];
    unset($srcParent['children'][$srcName]);
}

function cmd_grep(&$out, &$ctx, $args){
    $ci = in_array('-i', $args);
    $rest = array_values(array_filter($args, fn($a)=>$a!=='-i'));
    if(count($rest) < 2){ outp($out, 'usage: grep [-i] &lt;pattern&gt; &lt;file&gt;', 'out-warn'); return; }
    [$pattern, $target] = $rest;
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $target);
    $node = getRef($ctx['fsRoot'], $segs);
    if($node === null || $node['type'] !== 'file'){ fsError($out, 'grep', $target, 'No such file'); return; }
    foreach(explode("\n", $node['content']) as $l){
        $match = $ci ? stripos($l, $pattern) !== false : strpos($l, $pattern) !== false;
        if($match) outp($out, esc($l));
    }
}

function cmd_find(&$out, &$ctx, $args){
    $nameIdx = array_search('-name', $args);
    $pattern = $nameIdx !== false ? ($args[$nameIdx+1] ?? null) : null;
    $pathArg = '.';
    foreach($args as $i=>$a) if(!str_starts_with($a,'-') && $i !== $nameIdx+1){ $pathArg = $a; break; }
    $startSegs = resolvePath($ctx['cwd'], $ctx['home'], $pathArg);
    $startNode = getRef($ctx['fsRoot'], $startSegs);
    if($startNode === null){ fsError($out, 'find', $pathArg, 'No such file or directory'); return; }
    $regex = null;
    if($pattern){
        $esc = preg_quote($pattern, '/');
        $esc = str_replace('\*', '.*', $esc);
        $regex = '/^'.$esc.'$/';
    }
    $results = [];
    $walk = function($node, $segs) use (&$walk, &$results, $regex){
        $name = end($segs) ?: '';
        if(!$regex || preg_match($regex, $name)) $results[] = '/'.implode('/', $segs);
        if($node['type'] === 'dir') foreach($node['children'] as $k=>$child) $walk($child, array_merge($segs,[$k]));
    };
    $walk($startNode, $startSegs);
    foreach($results as $r) outp($out, esc($r ?: '/'));
}

function cmd_wc(&$out, &$ctx, $args){
    $linesOnly = in_array('-l', $args);
    $target = null;
    foreach($args as $a) if(!str_starts_with($a,'-')){ $target = $a; break; }
    if(!$target){ outp($out, 'usage: wc [-l] &lt;file&gt;', 'out-warn'); return; }
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $target);
    $node = getRef($ctx['fsRoot'], $segs);
    if($node === null || $node['type'] !== 'file'){ fsError($out, 'wc', $target, 'No such file'); return; }
    $lines = count(explode("\n", $node['content']));
    $words = count(array_filter(preg_split('/\s+/', $node['content'])));
    $chars = strlen($node['content']);
    outp($out, $linesOnly ? "$lines ".esc($target) : "$lines $words $chars ".esc($target));
}

function headTail(&$out, &$ctx, $args, $isHead){
    $n = 10;
    $nIdx = array_search('-n', $args);
    if($nIdx !== false) $n = intval($args[$nIdx+1] ?? 10);
    $target = null;
    foreach($args as $i=>$a) if(!str_starts_with($a,'-') && $i !== $nIdx+1 && ($args[$i-1] ?? null) !== '-n'){ $target = $a; break; }
    if(!$target){ outp($out, 'usage: '.($isHead?'head':'tail').' [-n N] &lt;file&gt;', 'out-warn'); return; }
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $target);
    $node = getRef($ctx['fsRoot'], $segs);
    if($node === null || $node['type'] !== 'file'){ fsError($out, $isHead?'head':'tail', $target, 'No such file'); return; }
    $lines = explode("\n", $node['content']);
    $slice = $isHead ? array_slice($lines,0,$n) : array_slice($lines,-$n);
    foreach($slice as $l) outp($out, esc($l));
}

function cmd_chmod(&$out, &$ctx, $args){
    if(count($args) < 2){ outp($out, 'usage: chmod &lt;mode&gt; &lt;file&gt;', 'out-warn'); return; }
    [$mode, $target] = $args;
    $segs = resolvePath($ctx['cwd'], $ctx['home'], $target);
    $node = &getRef($ctx['fsRoot'], $segs);
    if($node === null){ fsError($out, 'chmod', $target, 'No such file or directory'); return; }
    if(preg_match('/^[0-7]{3}$/', $mode)){
        $map = ['0'=>'---','1'=>'--x','2'=>'-w-','3'=>'-wx','4'=>'r--','5'=>'r-x','6'=>'rw-','7'=>'rwx'];
        $node['perm'] = '-'.implode('', array_map(fn($d)=>$map[$d], str_split($mode)));
    }
    outp($out, "mode of '".esc($target)."' changed to ".esc($mode), 'out-muted');
}

function cmd_ps(&$out, &$ctx){
    $procs = $ctx['kind'] === 'local'
        ? [['PID','CMD'],['1','init'],['214','bash'],['318','cyberarena-shell']]
        : [['PID','CMD'],['1','init'],['88','sshd'],['214','bash'],['940','apache2']];
    foreach($procs as $i=>$p){
        $line = str_pad($p[0],6).esc($p[1]);
        outp($out, $i===0 ? "<span class=\"out-muted\">$line</span>" : $line);
    }
}
function cmd_id(&$out, &$ctx){ outp($out, "uid=1000({$ctx['user']}) gid=1000({$ctx['user']}) groups=1000({$ctx['user']})"); }
function cmd_uname(&$out, &$ctx, $args){
    if(in_array('-a',$args)) outp($out, "Linux {$ctx['host']} 5.15.0-server #1 SMP x86_64 GNU/Linux");
    else outp($out, 'Linux');
}
function cmd_sudo(&$out, &$ctx, $args){
    if(($args[0] ?? null) === '-l'){
        if($ctx['kind'] === 'ssh'){
            outp($out, "Matching Defaults entries for {$ctx['user']} on {$ctx['host']}:", 'out-muted');
            outp($out, "User {$ctx['user']} may run the following commands on {$ctx['host']}:", 'out-muted');
            outp($out, "    (root) NOPASSWD: /usr/bin/systemctl status *");
        } else {
            outp($out, 'User recruit may run the following commands on cyberarena:', 'out-muted');
            outp($out, '    (ALL) ALL');
        }
    } else {
        outp($out, 'sudo: this system only supports `sudo -l`', 'out-warn');
    }
}
function cmd_history(&$out, $history){
    foreach($history as $i=>$h) outp($out, '  '.($i+1).'  '.esc($h), 'out-muted');
}

function cmd_nmap(&$out, &$g, $arg){
    if(!$arg){ outp($out, 'usage: nmap &lt;host&gt;', 'out-warn'); return; }
    $net = getNet();
    if(!isset($net[$arg])){ outp($out, "nmap: no route to host ".esc($arg), 'out-error'); return; }
    $host = $net[$arg];
    $g['scanned'][$arg] = true;
    outp($out, "Starting scan on ".esc($arg)." (".esc($host['name']).") ...", 'out-muted');
    $rows = '<table class="nmap"><tr><td class="out-muted">PORT</td><td class="out-muted">STATE</td><td class="out-muted">SERVICE</td><td class="out-muted">VERSION</td></tr>';
    foreach($host['ports'] as $p){
        $rows .= "<tr><td>{$p['port']}/tcp</td><td class=\"out-ok\">open</td><td>".esc($p['svc'])."</td><td class=\"out-muted\">".esc($p['ver'])."</td></tr>";
    }
    $rows .= '</table>';
    outp($out, $rows);
    if($arg === '10.10.14.2' && $g['stage'] === 0) advanceStage($g, $out, 1);
}

function cmd_open(&$out, &$g, $arg, &$widget){
    if(!$arg){ outp($out, 'usage: open &lt;host&gt;', 'out-warn'); return; }
    $net = getNet();
    if(!isset($net[$arg])){ outp($out, "open: no route to host ".esc($arg), 'out-error'); return; }
    if(empty($g['scanned'][$arg])){
        outp($out, 'open: connection refused (scan the host first with nmap)', 'out-error');
        addTrace($g, $out, 3, 'blind connection attempt');
        return;
    }
    $host = $net[$arg];
    if(isset($host['web'])){
        outp($out, 'GET / HTTP/1.1 → 200 OK', 'out-muted');
        outp($out, '<pre style="margin:0;white-space:pre-wrap">'.esc($host['web']['html']).'</pre>');
        if($g['stage'] === 1){
            $g['knownCreds'][$arg] = ['user'=>'admin','pass'=>'admin123'];
            advanceStage($g, $out, 2);
        }
    } elseif(isset($host['sqli'])){
        $widget = $arg;
    }
}

function handleSqliAttempt(&$g, &$out, $hostKey, $user, $pass){
    $net = getNet();
    $injected = preg_match("/'\s*or\s*'?1'?\s*=\s*'?1/i", $user) || preg_match("/'\s*or\s*'?1'?\s*=\s*'?1/i", $pass) || strpos($user, '--') !== false;
    outp($out, "[open:".esc($hostKey)."] login submitted: user=\"".esc($user)."\" pass=\"".esc($pass)."\"", 'out-muted');
    if($injected){
        outp($out, 'Query became truthy for every row — authentication bypassed.', 'out-ok');
        outp($out, '<span class="flag">'.esc($net[$hostKey]['sqli']['flag']).'</span>', 'out-ok');
        outp($out, "This works because input is dropped straight into the SQL string instead of a parameterized query. Real fix: prepared statements / bound parameters.");
        advanceStage($g, $out, 4);
    } else {
        outp($out, 'Access denied.', 'out-error');
        addTrace($g, $out, 4, 'failed login attempt logged');
    }
}

function cmd_ssh(&$out, &$g, $arg){
    if(!preg_match('/^([^@]+)@(.+)$/', $arg ?? '', $m)){ outp($out, 'usage: ssh user@host', 'out-warn'); return; }
    [, $user, $host] = $m;
    $net = getNet();
    if(!isset($net[$host])){ outp($out, "ssh: could not resolve host ".esc($host), 'out-error'); return; }
    if(empty($g['scanned'][$host])){ outp($out, 'ssh: connection timed out (scan the host first)', 'out-error'); return; }
    $hasSsh = false;
    foreach($net[$host]['ports'] as $p) if($p['svc']==='ssh') $hasSsh = true;
    if(!$hasSsh){ outp($out, "ssh: connect to host ".esc($host)." port 22: Connection refused", 'out-error'); return; }
    outp($out, "Connecting to ".esc($host)." as ".esc($user)." ...", 'out-muted');
    $g['awaitingPassword'] = ['host'=>$host,'user'=>$user];
}

function submitPassword(&$g, &$out, $pw){
    $host = $g['awaitingPassword']['host']; $user = $g['awaitingPassword']['user'];
    $g['awaitingPassword'] = null;
    $net = getNet();
    $target = $net[$host];
    $good = isset($target['ssh']) && $target['ssh']['validUser'] === $user && $target['ssh']['validPass'] === $pw;
    if($good){
        outp($out, 'Authenticating... success.', 'out-ok');
        $home = ['home', $user];
        $g['contexts'][] = [
            'kind'=>'ssh','user'=>$user,'host'=>$host,'hostKey'=>$host,
            'fsRoot'=>buildTree($target['ssh']['flat']), 'home'=>$home, 'cwd'=>$home,
        ];
        $g['knownCreds'][$host] = ['user'=>$user,'pass'=>$pw];
        if($g['stage'] <= 2) outp($out, "You're in. `ls` to see what's here.", 'out-cyan');
    } else {
        outp($out, 'Permission denied (password).', 'out-error');
        addTrace($g, $out, 8, 'failed ssh authentication');
    }
}

function cmd_exit(&$out, &$g){
    if(count($g['contexts']) <= 1){ outp($out, 'exit: no active ssh session', 'out-warn'); return; }
    $c = array_pop($g['contexts']);
    outp($out, "Connection to ".esc($c['host'])." closed.", 'out-muted');
}

function cmd_debrief(&$out){
    outp($out, '── DEBRIEF ──────────────────────────────', 'out-cyan');
    outp($out, '1. Recon before contact: scanning first is how real assessments start.');
    outp($out, '2. Information disclosure: comments and staging notes routinely leak real credentials in the wild.');
    outp($out, '3. Weak/default credentials remain one of the top initial-access vectors in real breaches.');
    outp($out, "4. SQL injection: unsanitized input concatenated into a query lets an attacker rewrite its logic. Fix = parameterized queries.");
    outp($out, 'None of this touched a real system — every host and file here is server-side session state.', 'out-muted');
}

function cmd_reset(&$g, &$out){
    $g['trace'] = 0;
    $g['contexts'] = [$g['contexts'][0]];
    $g['awaitingPassword'] = null;
    outp($out, 'Trace cleared. Sessions dropped.', 'out-muted');
}

// tokenizer respecting quotes
function tokenize($str){
    preg_match_all('/"([^"]*)"|\'([^\']*)\'|(\S+)/', $str, $m, PREG_SET_ORDER);
    $out = [];
    foreach($m as $t) $out[] = $t[1] !== '' ? $t[1] : ($t[2] !== '' ? $t[2] : $t[3]);
    return $out;
}

// ============================================================
// Main dispatcher. Returns [lines, widget(host or null), cleared(bool)]
// ============================================================
function runCommand(&$g, $raw){
    $out = [];
    $widget = null;
    $cleared = false;
    $text = trim($raw);
    if($text === '') return [$out, $widget, $cleared];

    if($g['awaitingPassword']){
        submitPassword($g, $out, $text);
        return [$out, $widget, $cleared];
    }

    $parts = tokenize($text);
    $cmd = strtolower($parts[0]);
    $args = array_slice($parts, 1);
    $ctx = &curCtx($g);

    switch($cmd){
        case 'help': cmd_help($out); break;
        case 'man': cmd_man($out, $args[0] ?? null); break;
        case 'nmap': cmd_nmap($out, $g, $args[0] ?? null); break;
        case 'open': cmd_open($out, $g, $args[0] ?? null, $widget); break;
        case 'ssh': cmd_ssh($out, $g, $args[0] ?? null); break;
        case 'ls': cmd_ls($out, $ctx, $args); break;
        case 'cd': cmd_cd($out, $ctx, $args); break;
        case 'pwd': cmd_pwd($out, $ctx); break;
        case 'cat': cmd_cat($out, $g, $ctx, $args); break;
        case 'echo': cmd_echo($out, $ctx, trim(substr($text, 4))); break;
        case 'mkdir': cmd_mkdir($out, $ctx, $args); break;
        case 'touch': cmd_touch($out, $ctx, $args); break;
        case 'rm': cmd_rm($out, $ctx, $args); break;
        case 'cp': cmd_cp($out, $ctx, $args); break;
        case 'mv': cmd_mv($out, $ctx, $args); break;
        case 'grep': cmd_grep($out, $ctx, $args); break;
        case 'find': cmd_find($out, $ctx, $args); break;
        case 'wc': cmd_wc($out, $ctx, $args); break;
        case 'head': headTail($out, $ctx, $args, true); break;
        case 'tail': headTail($out, $ctx, $args, false); break;
        case 'chmod': cmd_chmod($out, $ctx, $args); break;
        case 'whoami': outp($out, esc($ctx['user'])); break;
        case 'id': cmd_id($out, $ctx); break;
        case 'uname': cmd_uname($out, $ctx, $args); break;
        case 'date': outp($out, date('D M j H:i:s Y')); break;
        case 'ps': cmd_ps($out, $ctx); break;
        case 'sudo': cmd_sudo($out, $ctx, $args); break;
        case 'history': cmd_history($out, $g['history']); break;
        case 'exit': cmd_exit($out, $g); break;
        case 'status':
            $missions = getMissions();
            outp($out, '['.esc($missions[$g['stage']]['title']).'] '.esc($missions[$g['stage']]['brief']), 'out-cyan');
            break;
        case 'debrief': cmd_debrief($out); break;
        case 'reset': cmd_reset($g, $out); break;
        case 'clear': $cleared = true; break;
        default:
            outp($out, "command not found: ".esc($cmd)." (try 'help')", 'out-error');
    }
    return [$out, $widget, $cleared];
}
