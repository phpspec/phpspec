<?php

use PhpSpec\Console\Command\Pair\TerminalKeys;

describe(TerminalKeys::class, function () {

    beforeEach(function () {
        // Windows pairs sockets over loopback only.
        $domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;
        [$this->typed, $this->read] = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->keys = new TerminalKeys($this->read);
    });

    afterEach(function () {
        is_resource($this->typed) && fclose($this->typed);
        fclose($this->read);
    });

    it('reads a character, a multibyte one whole', function () {
        fwrite($this->typed, 'aé');

        expect($this->keys->next())->toBe('a');
        expect($this->keys->next())->toBe('é');
    });

    it('reads an escape sequence whole, parameters and all', function () {
        fwrite($this->typed, "\033[3~\033[1;3D\033[13;2u\033[A");

        expect($this->keys->next())->toBe("\033[3~");
        expect($this->keys->next())->toBe("\033[1;3D");
        expect($this->keys->next())->toBe("\033[13;2u");
        expect($this->keys->next())->toBe("\033[A");
    });

    it('reads Esc and the key after it as one, as Meta sends them', function () {
        fwrite($this->typed, "\033b\033\r\033OH");

        expect($this->keys->next())->toBe("\033b");
        expect($this->keys->next())->toBe("\033\r");
        expect($this->keys->next())->toBe("\033OH");
    });

    it('reads Esc alone when nothing follows it', function () {
        fwrite($this->typed, "\033");

        expect($this->keys->next())->toBe("\033");
    });

    it('reads a paste whole, line breaks and all', function () {
        fwrite($this->typed, "\033[200~one\ntwo\033[201~x");

        expect($this->keys->next())->toBe("\033[200~one\ntwo\033[201~");
        expect($this->keys->next())->toBe('x');
    });

    it('reads the end of input as Ctrl-D', function () {
        fclose($this->typed);

        expect($this->keys->next())->toBe("\004");
    });
});
