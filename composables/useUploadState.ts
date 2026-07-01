export const useUploadState = () => {
  const uploading = useState('uploading', () => false)
  const currentFile = useState('uploadCurrentFile', () => 0)
  const totalFiles = useState('uploadTotalFiles', () => 0)
  const currentFileName = useState('uploadCurrentFileName', () => '')
  const uploadError = useState('uploadError', () => '')

  function startUpload(total: number) {
    uploading.value = true
    currentFile.value = 0
    totalFiles.value = total
    uploadError.value = ''
  }

  function updateProgress(current: number, fileName: string) {
    currentFile.value = current
    currentFileName.value = fileName
  }

  function finishUpload(error?: string) {
    if (error) {
      uploadError.value = error
      setTimeout(() => { uploadError.value = '' }, 5000)
    }
    uploading.value = false
    currentFile.value = 0
    totalFiles.value = 0
    currentFileName.value = ''
  }

  return {
    uploading,
    currentFile,
    totalFiles,
    currentFileName,
    uploadError,
    startUpload,
    updateProgress,
    finishUpload,
  }
}
