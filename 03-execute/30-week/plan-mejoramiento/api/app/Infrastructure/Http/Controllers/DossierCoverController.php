<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Service\DossierCoverService;
use App\Infrastructure\Http\Input;
use App\Infrastructure\Http\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

/** /dossiers/{id}/cover — carátula digitalizada (CA-03.4). */
final class DossierCoverController
{
    public function __construct(private readonly DossierCoverService $covers) {}

    /** PUT multipart/form-data con el campo `file`. El tipo se detecta por el CONTENIDO (api-contract §4). */
    public function update(Request $request, string $id): JsonResponse
    {
        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            $input = new Input($request);
            $input->addError('file', 'se requiere un archivo JPG o PNG de máximo 2 MB');
            $input->validate();
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
        $dossier = $this->covers->upload($id, $bytes, $mime);

        return new JsonResponse(Presenter::dossier($dossier), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show(string $id): Response
    {
        $cover = $this->covers->get($id);

        return new Response($cover['bytes'], 200, ['Content-Type' => $cover['mime']]);
    }

    public function destroy(string $id): Response
    {
        $this->covers->remove($id);

        return new Response(null, 204);
    }
}
