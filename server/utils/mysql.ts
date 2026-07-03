import mysql from 'mysql2/promise'

let pool: mysql.Pool | null = null

function getPool(): mysql.Pool {
  if (!pool) {
    const config = useRuntimeConfig()
    pool = mysql.createPool({
      host: config.mysqlHost,
      port: parseInt(config.mysqlPort || '3306'),
      user: config.mysqlUser,
      password: config.mysqlPassword,
      database: config.mysqlDatabase,
      waitForConnections: true,
      connectionLimit: 10,
      queueLimit: 0,
    })
  }
  return pool
}

export async function dbQuery(sql: string, params?: any[]): Promise<any> {
  const pool = getPool()
  const [rows] = await pool.execute(sql, params)
  return rows
}

export async function dbGet(sql: string, params?: any[]): Promise<any> {
  const pool = getPool()
  const [rows] = await pool.execute(sql, params)
  return (rows as any[])[0] || null
}

export async function dbRun(sql: string, params?: any[]): Promise<any> {
  const pool = getPool()
  const [result] = await pool.execute(sql, params)
  return result
}
