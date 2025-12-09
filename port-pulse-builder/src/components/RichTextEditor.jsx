import React from 'react';
import ReactQuill from 'react-quill';

const toolbarOptions = [
  [{ header: [1, 2, 3, false] }],
  ['bold', 'italic', 'underline', 'strike'],
  [{ color: [] }, { background: [] }],
  [{ list: 'ordered' }, { list: 'bullet' }],
  [{ align: [] }],
  ['link'],
  ['clean']
];

export default function RichTextEditor({ label, value, onChange, helperText }) {
  return (
    <div className="space-y-1">
      {label && (
        <label className="block text-xs font-medium text-slate-600">
          {label}
        </label>
      )}
      <ReactQuill
        theme="snow"
        value={value}
        onChange={onChange}
        modules={{ toolbar: toolbarOptions }}
      />
      {helperText && (
        <p className="text-[10px] text-slate-400">
          {helperText}
        </p>
      )}
    </div>
  );
}
