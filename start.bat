@ECHO OFF
chcp 65001>nul
set arg1=%1
set arg2=%2
set arg3=%3
set arg4=%4
set arg5=%5
set arg6=%6
set arg7=%7
set arg8=%8
set arg9=%9
shift
set arg10=%9
shift
set arg11=%9
shift
set arg12=%9
shift
set arg13=%9
%b2eincfilepath%\php.exe %b2eincfilepath%\run.php %arg1% %arg2% %arg3% %arg4% %arg5% %arg6% %arg7% %arg8% %arg9% %arg10% %arg11% %arg12% %arg13%
