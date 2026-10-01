<?php
declare(strict_types=1);

return [

    /*
     * Image generation API.
     */
    'endpoint' =>
        'http://127.0.0.1:7861/sdapi/v1/txt2img',

    /*
     * Default request parameters.
     *
     * These are passed directly to the configured backend.
     */
    'request' => [
        'negative_prompt' =>
            'blurry, low quality, distorted, extra limbs, bad anatomy, deformed',

        'steps'               => 20,
        'width'               => 512,
        'height'              => 512,
        'cfg_scale'           => 7.0,
        'sampler_name'        => 'k_dpmpp_2m',
        'scheduler'           => 'karras',
        'sd_model_checkpoint' =>
            'SDXL_bigLust_v16.safetensors'
    ],

    /*
     * Generated image storage.
     */
    'save_path' =>
        DIR_ROOT.'/public/genimages',

    /*
     * Public URL path.
     */
    'public_path' =>
        '/genimages'
];
?>