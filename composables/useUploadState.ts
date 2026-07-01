export const useUploadState = () => {
  const uploading = useState('uploading', () => false)
  const currentFile = useState('uploadCurrentFile', () => 0)
  const totalFiles = useState('uploadTotalFiles', () => 0)
  const currentFileName = useState('uploadCurrentFileName', () => '')
  const uploadError = useState('uploadError', () => '')
  const cancelled = useState('uploadCancelled', () => false)
  const uploadStartTime = useState('uploadStartTime', () => 0)
  const avgPerMinute = useState('uploadAvgPerMinute', () => 0)

  function startUpload(total: number) {
    uploading.value = true
    currentFile.value = 0
    totalFiles.value = total
    uploadError.value = ''
    cancelled.value = false
    uploadStartTime.value = Date.now()
    avgPerMinute.value = 0
  }

  function updateProgress(current: number, fileName: string) {
    currentFile.value = current
    currentFileName.value = fileName
    if (current > 0 && uploadStartTime.value > 0) {
      const elapsed = (Date.now() - uploadStartTime.value) / 60000
      avgPerMinute.value = elapsed > 0 ? Math.round((current / elapsed) * 10) / 10 : 0
    }
  }

  function getEstimatedSeconds(): number {
    if (avgPerMinute.value <= 0 || totalFiles.value <= currentFile.value) return 0
    const remaining = totalFiles.value - currentFile.value
    return Math.round((remaining / avgPerMinute.value) * 60)
  }

  function cancelUpload() {
    cancelled.value = true
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
    cancelled.value = false
    uploadStartTime.value = 0
    avgPerMinute.value = 0
  }

  return {
    uploading,
    currentFile,
    totalFiles,
    currentFileName,
    uploadError,
    cancelled,
    avgPerMinute,
    startUpload,
    updateProgress,
    getEstimatedSeconds,
    cancelUpload,
    finishUpload,
  }
}
