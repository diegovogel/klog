<?php

describe('health check', function () {
    it('is disabled by default', function () {
        $this->get('/up')->assertNotFound();
    });
});
