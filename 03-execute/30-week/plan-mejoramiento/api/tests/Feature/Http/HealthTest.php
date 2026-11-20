<?php

// T-01 · GET /api/v1/health es público y responde {"status":"ok"}.
it('responde ok sin token', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});
