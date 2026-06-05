@include('calendar._post_images', [
  'images' => $images,
  'postId' => $postId,
  'containerClass' => 'flex gap-2 mt-2.5',
  'linkClass' => 'image-preview-link block shrink-0 cursor-zoom-in',
  'imgClass' => 'w-16 h-16 rounded-lg object-cover border border-slate-800/80 hover:opacity-80 transition'
])
