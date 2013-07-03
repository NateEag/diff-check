Testing
=======

This file would do very poorly if syntax-checked by PHP, or run through PHP
CodeSniffer.

Therefore, it would behoove this tool if it did not try to do so.

As it happens, CodeSniffer itself by default will only check a limited range of file extensions, but we can save a little processing time by not even looking at files with an extension other than .php.
