import { vi } from 'vitest'
import { api, ApiError, toQueryString, tokenStore } from './api'

describe('toQueryString', () => {
  it('serializes filters the way Laravel expects', () => {
    expect(toQueryString({ vegetarian: true, max_price: 25, exclude_allergens: ['peanuts', 'gluten'], search: '', category: undefined })).toBe(
      '?vegetarian=1&max_price=25&exclude_allergens=peanuts%2Cgluten',
    )
    expect(toQueryString({})).toBe('')
  })
})

describe('apiFetch', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('sends the bearer token and returns the JSON body', async () => {
    tokenStore.set('1|secret')
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [] }), { status: 200 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(api.get('/dishes', { vegan: true })).resolves.toEqual({ data: [] })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/dishes?vegan=1')
    expect(init.headers.Authorization).toBe('Bearer 1|secret')
  })

  it('turns the Laravel error envelope into an ApiError', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        new Response(
          JSON.stringify({ error: { status: 422, code: 'validation_failed', message: 'Invalid.', details: { email: ['The email is taken.'] } } }),
          { status: 422 },
        ),
      ),
    )

    const error = (await api.post('/auth/register', {}).catch((e: unknown) => e)) as ApiError

    expect(error).toBeInstanceOf(ApiError)
    expect(error.code).toBe('validation_failed')
    expect(error.fieldError('email')).toBe('The email is taken.')
  })

  it('forgets the token on 401', async () => {
    tokenStore.set('expired')
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ error: { code: 'unauthenticated' } }), { status: 401 })))

    await api.get('/auth/me').catch(() => undefined)

    expect(tokenStore.get()).toBeNull()
  })
})
