Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""C:\Users\Administrator\OneDrive\Desktop\LHPRIA deploy\tunnel_keeper.ps1""", 0, False
