function getResolveFn(): ((value: boolean) => void) | null {
  return (globalThis as any).__dialogResolveFn || null
}

function setResolveFn(fn: ((value: boolean) => void) | null) {
  (globalThis as any).__dialogResolveFn = fn
}

export const useDialog = () => {
  const show = useState('dialogShow', () => false)
  const type = useState<'alert' | 'confirm'>('dialogType', () => 'alert')
  const title = useState('dialogTitle', () => '')
  const message = useState('dialogMessage', () => '')

  function alert(msg: string, t?: string) {
    type.value = 'alert'
    title.value = t || 'Informasi'
    message.value = msg
    show.value = true
    return new Promise<boolean>((resolve) => {
      setResolveFn(resolve)
    })
  }

  function confirm(msg: string, t?: string) {
    type.value = 'confirm'
    title.value = t || 'Konfirmasi'
    message.value = msg
    show.value = true
    return new Promise<boolean>((resolve) => {
      setResolveFn(resolve)
    })
  }

  function resolve(value: boolean) {
    show.value = false
    const fn = getResolveFn()
    if (fn) fn(value)
    setResolveFn(null)
  }

  return { show, type, title, message, alert, confirm, resolve }
}
