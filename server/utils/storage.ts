export interface Album {
  id: string
  name: string
  paket: string
  drive_link: string
  folder_id: string
  group_name: string
  expires_at: number
  created_at: number
}

export interface Database {
  [id: string]: Album
}

export async function getDb(): Promise<Database> {
  const storage = useStorage('db')
  const data = await storage.getItem<Database>('database.json')
  return data || {}
}

export async function saveDb(db: Database): Promise<void> {
  const storage = useStorage('db')
  await storage.setItem('database.json', db)
}

export function getDriveFolderId(url: string): string | null {
  const match = url.match(/(?:folders\/|id=)([\w-]+)/)
  return match?.[1] || null
}

export function generateAlbumId(): string {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
  let result = ''
  for (let i = 0; i < 6; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  return result
}
