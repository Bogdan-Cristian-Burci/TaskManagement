<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Project Document Configuration
    |--------------------------------------------------------------------------
    |
    | Configure limits and restrictions for project document attachments.
    |
    */

    'project_documents' => [
        // Maximum number of documents per project
        'max_files' => env('PROJECT_MAX_DOCUMENTS', 10),

        // Maximum file size in KB (20MB = 20480 KB)
        'max_file_size' => env('PROJECT_MAX_FILE_SIZE', 20480),

        // Allowed MIME types for document uploads
        'allowed_mime_types' => [
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            
            // Images
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            
            // Archives
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip',
        ],

        // File extension to MIME type mapping for validation
        'allowed_extensions' => [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'gz' => 'application/gzip',
        ],

        // Media collections configuration
        'collections' => [
            'documents' => [
                'disk' => 'public',
                'path' => 'project-documents',
                'conversions' => [
                    'thumbnail' => [
                        'width' => 150,
                        'height' => 150,
                        'crop' => true,
                    ],
                ],
            ],
            'attachments' => [
                'disk' => 'public', 
                'path' => 'project-attachments',
                'conversions' => [
                    'thumbnail' => [
                        'width' => 150,
                        'height' => 150,
                        'crop' => true,
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Attachment Configuration
    |--------------------------------------------------------------------------
    |
    | Configure limits for task attachments (if different from project docs)
    |
    */

    'task_attachments' => [
        'max_files' => env('TASK_MAX_ATTACHMENTS', 5),
        'max_file_size' => env('TASK_MAX_FILE_SIZE', 10240), // 10MB
    ],
];