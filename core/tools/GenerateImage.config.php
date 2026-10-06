<?php
declare(strict_types=1);

return [
    /*
     * Backend selection and URLs come from database settings:
     * system.image_backend, system.forge_url, system.qwen2_url
     */
    'backends' => [
        'forge' => [
            'timeout' => 300,
            'request' => [
                'negative_prompt' =>
                    'blurry, low quality, distorted, extra limbs, bad anatomy, deformed',
                'steps' => 20,
                'width' => 512,
                'height' => 512,
                'cfg_scale' => 7.0,
                'sampler_name' => 'k_dpmpp_2m',
                'scheduler' => 'karras',
                'sd_model_checkpoint' => 'SDXL_bigLust_v16.safetensors',
            ],
        ],
        'qwen2' => [
            'timeout' => 600,
            'request' => [
                'steps' => 20,
                'width' => 1024,
                'height' => 1024,
                'cfg_scale' => 1.0,
                'sampler_name' => 'Euler',
                'seed' => -1,
                'batch_size' => 1,
            ],
        ],
    ],

    'save_path' => DIR_ROOT . '/public/genimages',
    'public_path' => '/genimages',
];
