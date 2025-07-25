<?php

namespace App\Services\Post;

use App\Models\Post;
use App\Services\Image\CreateImage;
use App\Services\Image\DeleteImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatePost
{
    public function __construct(
        protected CreateImage $createImageService,
        protected DeleteImage $deleteImageService
    ) {
    }

    /**
     * Cria um novo post e sua imagem associada.
     *
     * @param array $data
     * @return Post
     * @throws Throwable
     */
    public function create(array $data): Post
    {
        $imageData = null;

        try {
            /** @var UploadedFile|null $imageFile */
            if ($imageFile = data_get($data, 'image')) {
                $imageData = $this->createImageService->uploadOnly($imageFile);
            }

            $post = DB::transaction(function () use ($data, $imageData) {
                $data['user_id'] = Auth::id();
                $post = Post::create($data);

                if ($topics = data_get($data, 'topics', [])) {
                    $post->topics()->sync($topics);
                }

                if ($imageData) {
                    $this->createImageService->createDbRecord($post, $imageData);
                }

                return $post;
            });

            return $post;

        } catch (Throwable $e) {
            if ($imageData) {
                Log::info('Rolling back file upload due to DB transaction failure.', [
                    'path' => $imageData['path'],
                    'error' => $e->getMessage(),
                ]);
                $this->deleteImageService->deleteFile($imageData['path']);
            }

            throw $e;
        }
    }
}
