export const useDialog = () => {
  const show = useState('dialogShow', () => false)
  const type = useState<'alert' | 'confirm'>('dialogType', () => 'alert')
  const title = useState('dialogTitle', () => '')
  const message = useState('dialogMessage', () => '')
  let resolveFn: ((value: boolean) => void) | null = null

  function alert(msg: string, t?: string) {
    type.value = 'alert'
    title.value = t || 'Informasi'
    message.value = msg
    show.value = true
    return new Promise<boolean>((resolve) => {
      resolveFn = resolve
    })
  }

  function confirm(msg: string, t?: string) {
    type.value = 'confirm'
    title.value = t || 'Konfirmasi'
    message.value = msg
    show.value = true
    return new Promise<boolean>((resolve) => {
      resolveFn = resolve
    })
  }

  function resolve(value: boolean) {
    show.value = false
    if (resolveFn) resolveFn(value)
    resolveFn = null
  }

  return { show, type, title, message, alert, confirm, resolve }
}
