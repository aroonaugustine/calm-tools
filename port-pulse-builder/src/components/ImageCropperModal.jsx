import React, { useState, useCallback } from 'react';
import Cropper from 'react-easy-crop';

function getCroppedImg(imageSrc, crop, zoom, aspect = 4 / 3) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.crossOrigin = 'anonymous';
    image.src = imageSrc;

    image.onload = () => {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');

      const naturalWidth = image.naturalWidth;
      const naturalHeight = image.naturalHeight;

      const cropX = (crop.x / 100) * naturalWidth;
      const cropY = (crop.y / 100) * naturalHeight;

      const scaledWidth = naturalWidth / zoom;
      const scaledHeight = naturalHeight / zoom;

      const cropWidth = scaledWidth;
      const cropHeight = scaledHeight;

      canvas.width = cropWidth;
      canvas.height = cropHeight;

      ctx.drawImage(
        image,
        cropX,
        cropY,
        cropWidth,
        cropHeight,
        0,
        0,
        cropWidth,
        cropHeight
      );

      const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
      resolve(dataUrl);
    };

    image.onerror = (err) => {
      reject(err);
    };
  });
}

export default function ImageCropperModal({
  open,
  title = 'Crop image',
  src,
  aspect = 4 / 3,
  onCancel,
  onApply
}) {
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);

  const onCropComplete = useCallback(() => {}, []);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/50">
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div className="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-slate-800">
            {title}
          </h2>
          <button
            onClick={onCancel}
            className="text-xs text-slate-500 hover:text-slate-800"
          >
            ✕
          </button>
        </div>

        <div className="relative w-full h-64 bg-slate-100">
          <Cropper
            image={src}
            crop={crop}
            zoom={zoom}
            aspect={aspect}
            onCropChange={setCrop}
            onZoomChange={setZoom}
            onCropComplete={onCropComplete}
          />
        </div>

        <div className="px-4 py-3 flex items-center justify-between gap-3 border-t border-slate-200">
          <div className="flex-1">
            <label className="block text-[11px] font-medium text-slate-600 mb-1">
              Zoom
            </label>
            <input
              type="range"
              min={1}
              max={3}
              step={0.1}
              value={zoom}
              onChange={(e) => setZoom(parseFloat(e.target.value))}
              className="w-full"
            />
          </div>
          <div className="flex-shrink-0 flex gap-2">
            <button
              onClick={onCancel}
              className="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              onClick={async () => {
                try {
                  const dataUrl = await getCroppedImg(src, crop, zoom, aspect);
                  onApply(dataUrl);
                } catch (e) {
                  console.error(e);
                  onCancel();
                }
              }}
              className="text-xs px-3 py-1.5 rounded-lg bg-slate-900 text-white hover:bg-slate-800"
            >
              Apply
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
