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

const ALBUMS_TABLE = 'albums'
const SETTINGS_TABLE = 'settings'

async function ensureTable() {
  await dbRun(`CREATE TABLE IF NOT EXISTS ${ALBUMS_TABLE} (
    id VARCHAR(10) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    paket VARCHAR(50) DEFAULT 'Self Photo',
    drive_link TEXT,
    folder_id VARCHAR(100),
    group_name VARCHAR(255) DEFAULT '',
    expires_at BIGINT DEFAULT 0,
    created_at BIGINT DEFAULT 0
  )`)
  await dbRun(`CREATE TABLE IF NOT EXISTS ${SETTINGS_TABLE} (
    \`key\` VARCHAR(255) PRIMARY KEY,
    value TEXT
  )`)
}

export async function getDb(): Promise<Database> {
  await ensureTable()
  const rows = await dbQuery(`SELECT * FROM ${ALBUMS_TABLE}`) as Album[]
  const db: Database = {}
  for (const row of rows) {
    db[row.id] = row
  }
  return db
}

export async function saveDb(db: Database): Promise<void> {
  await ensureTable()
  const entries = Object.entries(db).filter(([id]) => !id.startsWith('_'))
  await dbRun(`DELETE FROM ${ALBUMS_TABLE}`)
  if (entries.length > 0) {
    const placeholders = entries.map(() => '(?, ?, ?, ?, ?, ?, ?, ?)').join(',')
    const values = entries.flatMap(([id, album]) => [
      album.id, album.name, album.paket, album.drive_link,
      album.folder_id, album.group_name, album.expires_at, album.created_at
    ])
    await dbRun(`INSERT INTO ${ALBUMS_TABLE} (id, name, paket, drive_link, folder_id, group_name, expires_at, created_at) VALUES ${placeholders}`, values)
  }
}

export async function getSetting(key: string): Promise<any> {
  const row = await dbGet(`SELECT value FROM ${SETTINGS_TABLE} WHERE \`key\` = ?`, [key])
  if (!row?.value) return null
  try { return JSON.parse(row.value) } catch { return row.value }
}

export async function saveSetting(key: string, value: any): Promise<void> {
  await ensureTable()
  if (value === null) {
    await dbRun(`DELETE FROM ${SETTINGS_TABLE} WHERE \`key\` = ?`, [key])
    return
  }
  const str = typeof value === 'string' ? value : JSON.stringify(value)
  await dbRun(`INSERT INTO ${SETTINGS_TABLE} (\`key\`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?`, [key, str, str])
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
