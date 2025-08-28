# Media File Serving Fix - Setup Guide

## Problem Solved
This fix resolves the issue where uploaded media files couldn't be displayed in the Next.js frontend due to authentication barriers and CORS restrictions.

## Changes Made

### 1. Added Public Media Routes
New public API routes that don't require authentication:
- `GET /api/media/projects/{project}/documents/{media}` - Serve original file
- `GET /api/media/projects/{project}/documents/{media}/thumbnail` - Serve thumbnail
- `GET /api/media/projects/{project}/documents/{media}/preview` - Serve preview

### 2. Enhanced ProjectController
Added new methods with CORS support:
- `servePublicMedia()` - Serves original files
- `servePublicThumbnail()` - Serves thumbnails  
- `servePublicPreview()` - Serves previews
- `serveMediaFile()` - Common method with CORS headers

### 3. Updated URL Generation
Modified these components to use new public routes:
- `ProjectResource.php` - API resource responses
- `ProjectController::getMedia()` - Media listing endpoint  
- `ProjectController::uploadMedia()` - Upload response

## Setup Instructions

### 1. Create Storage Symlink
```bash
php artisan storage:link
```

### 2. Verify Storage Permissions
```bash
chmod -R 755 storage/
chmod -R 755 public/storage/
```

### 3. Test File Upload
Upload a file to a project and verify the response contains the new URLs:
```json
{
  "id": 123,
  "name": "image.jpg", 
  "url": "http://yourapi.com/api/media/projects/1/documents/123",
  "conversions": {
    "thumbnail": "http://yourapi.com/api/media/projects/1/documents/123/thumbnail",
    "preview": "http://yourapi.com/api/media/projects/1/documents/123/preview"
  }
}
```

### 4. Test Frontend Access
From your Next.js application, you should now be able to:
```javascript
// Direct image display
<img src="http://yourapi.com/api/media/projects/1/documents/123" alt="Document" />

// Thumbnail display  
<img src="http://yourapi.com/api/media/projects/1/documents/123/thumbnail" alt="Thumbnail" />
```

## Security Features

- **Project Validation**: Files are only served if they belong to the requested project
- **File Existence Check**: Returns 404 if file doesn't exist on disk
- **MIME Type Validation**: Proper content types are set
- **No Data Exposure**: Only serves files, no metadata exposed

## CORS Headers

All media endpoints include:
- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET`
- `Access-Control-Allow-Headers: Content-Type`
- `Cache-Control: public, max-age=31536000`

## Backwards Compatibility

Original authenticated routes remain available:
- `/api/projects/{project}/documents/{media}/download`
- `/api/projects/{project}/documents/{media}/thumbnail`  
- `/api/projects/{project}/documents/{media}/preview`

These require authentication and are suitable for admin interfaces.

## Troubleshooting

### Storage Link Issues
If you get "file not found" errors:
1. Run `php artisan storage:link`
2. Check that `public/storage` exists and points to `storage/app/public`
3. Verify file permissions

### CORS Issues
If frontend still can't access files:
1. Check browser developer tools for CORS errors
2. Verify your frontend domain is allowed
3. Consider restricting `Access-Control-Allow-Origin` to specific domains in production

### Performance Optimization
For production:
1. Use a CDN for media files
2. Configure proper caching headers
3. Consider using signed URLs for sensitive content

## Environment Variables

Consider adding these to your `.env`:
```env
# Media serving configuration
MEDIA_CACHE_DURATION=31536000
MEDIA_CORS_ORIGIN=*
MEDIA_PUBLIC_ACCESS=true
```