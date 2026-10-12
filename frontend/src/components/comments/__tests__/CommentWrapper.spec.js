import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, reactive } from 'vue'
import { setupVuetify } from '/tests/setupVuetify.js'
import CommentWrapper from '@/components/comments/CommentWrapper.vue'
import { commentsState, resetCommentsState } from '@/components/comments/commentsState.js'

setupVuetify()

function mountWrapper() {
  return mount(CommentWrapper, {
    global: {
      provide: { commentsController: reactive({ open: false }) },
      stubs: {
        VFab: { template: '<button class="fab"><slot /></button>' },
        VNavigationDrawer: true,
      },
    },
    slots: { default: '<div class="content" />' },
  })
}

describe('CommentWrapper', () => {
  afterEach(() => resetCommentsState())

  it('shows the anchored-comments fab while the comments panel is closed', () => {
    const wrapper = mountWrapper()
    expect(wrapper.find('.fab').exists()).toBe(true)
  })

  it('hides the fab while the comments panel is open, so it cannot cover the composer', async () => {
    const wrapper = mountWrapper()
    commentsState.open = true
    await nextTick()
    expect(wrapper.find('.fab').exists()).toBe(false)

    commentsState.open = false
    await nextTick()
    expect(wrapper.find('.fab').exists()).toBe(true)
  })
})
