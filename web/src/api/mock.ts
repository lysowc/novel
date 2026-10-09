// ============================================================
// 本地 Mock 数据层（默认关闭，走真实后端）
// 需要纯前端演示时：VITE_USE_MOCK=1 pnpm dev
// 接口形状与后端契约完全一致，替换为真实请求无需改页面
// ============================================================
import type {
  AiLog, AiModel, AiProvider, AiTask, Category, Chapter, ChapterListItem,
  ChapterRead, ConsistencyReport, Dashboard, HomeData, Idea, IdeaMessage,
  LoginResult, Memory, Novel, NovelDetail, NovelSetting, PageResult, Prompt,
  SystemConfig, TaskType,
} from '@/types/api'
import type { StreamHandlers } from './sse'
import { countWords } from '@/lib/format'

export const USE_MOCK: boolean = import.meta.env.VITE_USE_MOCK === '1'
export const MOCK_USERNAME = 'admin'
export const MOCK_PASSWORD = 'admin123'

const now = () => new Date().toISOString().replace('T', ' ').slice(0, 19)
const daysAgo = (n: number, h = 0) => {
  const d = new Date(Date.now() - n * 86400000 - h * 3600000)
  return d.toISOString().replace('T', ' ').slice(0, 19)
}

let idSeq = 1000
const nid = () => ++idSeq

// ---------------- 分类 ----------------
const categories: Category[] = [
  { id: 1, name: '玄幻', description: '修炼成仙、斗破苍穹', sort: 1, status: 1, created_at: daysAgo(90), updated_at: daysAgo(20) },
  { id: 2, name: '都市', description: '都市生活、职场商战', sort: 2, status: 1, created_at: daysAgo(90), updated_at: daysAgo(20) },
  { id: 3, name: '科幻', description: '星际、赛博朋克', sort: 3, status: 1, created_at: daysAgo(88), updated_at: daysAgo(15) },
  { id: 4, name: '悬疑', description: '推理、惊悚', sort: 4, status: 1, created_at: daysAgo(85), updated_at: daysAgo(12) },
  { id: 5, name: '言情', description: '都市情感、古风', sort: 5, status: 1, created_at: daysAgo(80), updated_at: daysAgo(10) },
  { id: 6, name: '历史', description: '架空历史、王朝争霸', sort: 6, status: 1, created_at: daysAgo(70), updated_at: daysAgo(5) },
]

// ---------------- 小说 ----------------
const novels: Novel[] = [
  {
    id: 1, category_id: 1, title: '剑起青山', cover: '', description:
      '少年沈砚被逐出宗门的那天，天上下着大雪。他带着一柄断剑和半本残卷离开青山城，没有人想到，十年后这个名字会让整座大陆为之震颤。', tags: '仙侠,成长,热血', status: 'published', is_public: 1,
    word_count: 128000, chapter_count: 5, created_at: daysAgo(60), updated_at: daysAgo(0, 3),
  },
  {
    id: 2, category_id: 3, title: '星海拾荒者', cover: '', description:
      '公元 2147 年，人类在太阳系边缘的废土星带捡拾上个文明的残骸。拾荒者陈默在一艘废弃飞船里，捡到了一段不属于人类的历史。', tags: '科幻,星际,冒险', status: 'published', is_public: 1,
    word_count: 86000, chapter_count: 0, created_at: daysAgo(55), updated_at: daysAgo(1, 6),
  },
  {
    id: 3, category_id: 2, title: '深夜食堂的老板娘', cover: '', description:
      '城市写字楼下的深夜食堂，只接待失意的客人。老板娘阿黎记得每一个人的故事，却想不起自己是谁。', tags: '都市,治愈,情感', status: 'published', is_public: 1,
    word_count: 64000, chapter_count: 0, created_at: daysAgo(50), updated_at: daysAgo(2, 2),
  },
  {
    id: 4, category_id: 4, title: '第七个目击者', cover: '', description:
      '雨夜的便利店，七个人同时目击了一场车祸。可七个人的证词，没有一个相同。刑警林深发现，他们之中有一个不是人。', tags: '悬疑,推理,反转', status: 'published', is_public: 1,
    word_count: 52000, chapter_count: 0, created_at: daysAgo(45), updated_at: daysAgo(3),
  },
  {
    id: 5, category_id: 5, title: '春风不度玉门关', cover: '', description:
      '她是边关守将之女，他是敌国质子。塞外黄沙十年，她等来的不是归人，而是一场蓄谋已久的和亲。', tags: '古言,虐恋,权谋', status: 'published', is_public: 1,
    word_count: 97000, chapter_count: 0, created_at: daysAgo(40), updated_at: daysAgo(4),
  },
  {
    id: 6, category_id: 6, title: '大明小吏', cover: '', description:
      '穿越到永乐年间，成了一名九品小吏。没有金手指，没有系统，只有一肚子的现代知识。且看小吏如何在大明朝堂搅动风云。', tags: '历史,穿越,权谋', status: 'published', is_public: 1,
    word_count: 156000, chapter_count: 0, created_at: daysAgo(35), updated_at: daysAgo(5),
  },
  {
    id: 7, category_id: 1, title: '焚天记', cover: '', description:
      '苍穹之上，九重天阙。少年陆沉身负焚天之火，从凡尘一路烧到凌霄，烧尽诸天神佛的伪善。', tags: '玄幻,热血,升级', status: 'draft', is_public: 0,
    word_count: 34000, chapter_count: 0, created_at: daysAgo(20), updated_at: daysAgo(1),
  },
  {
    id: 8, category_id: 3, title: '寂静的春天之后', cover: '', description:
      '全球植被一夜之间枯萎，只有一个小镇例外。生态学家苏晚来到小镇，发现这里的植物，正在模仿人类的记忆生长。', tags: '科幻,生态,悬疑', status: 'draft', is_public: 0,
    word_count: 21000, chapter_count: 0, created_at: daysAgo(15), updated_at: daysAgo(0, 8),
  },
  {
    id: 9, category_id: 2, title: '我在故宫修钟表', cover: '', description:
      '文物修复师顾言有一个秘密：他能听见钟表的记忆。直到有一天，一座沉睡三百年的西洋钟，对他说了一句话。', tags: '都市,奇谈,温情', status: 'published', is_public: 1,
    word_count: 73000, chapter_count: 0, created_at: daysAgo(30), updated_at: daysAgo(6),
  },
  {
    id: 10, category_id: 4, title: '雾锁迷城', cover: '', description:
      '一座常年被大雾笼罩的边城，每隔七年消失一个人。记者江雪追踪调查，却发现消失的人，都曾在梦里向她求救。', tags: '悬疑,惊悚,迷雾', status: 'finished', is_public: 1,
    word_count: 188000, chapter_count: 0, created_at: daysAgo(100), updated_at: daysAgo(30),
  },
  {
    id: 11, category_id: 5, title: '长安月下', cover: '', description:
      '开元盛世的长安，胡姬酒肆的灯火彻夜不熄。舞姬红绡与落魄诗人在月下相遇，一段跨越盛衰的传奇就此展开。', tags: '古言,盛唐,BE美学', status: 'published', is_public: 1,
    word_count: 112000, chapter_count: 0, created_at: daysAgo(28), updated_at: daysAgo(7),
  },
  {
    id: 12, category_id: 1, title: '万古神帝', cover: '', description:
      '被废的圣体，重生的神魂。少年叶尘踏碎虚空，问鼎万古神帝之位。', tags: '玄幻,重生,无敌流', status: 'published', is_public: 1,
    word_count: 210000, chapter_count: 0, created_at: daysAgo(110), updated_at: daysAgo(2, 9),
  },
]

const novelsById = () => Object.fromEntries(novels.map((n) => [n.id, n]))

function withCategoryName(n: Novel): Novel {
  const c = categories.find((x) => x.id === n.category_id)
  return { ...n, category_name: c?.name ?? '未分类' }
}

// ---------------- 章节（主小说 1 号有完整章节） ----------------
const chapterTexts: Record<number, string> = {
  1: `青山城外的雪下了一天一夜，沈砚跪在宗门大殿外的石阶上，膝盖早已没了知觉。

师父的声音从殿内传来，隔着厚重的门扉，听不出半分情绪："沈砚，你天资平庸，心性不坚，今日起，逐出青云门，永不得再入山门。"

他张了张嘴，想说什么，最后只是重重地磕了三个头。额头磕在冰冷的石阶上，渗出血来，融进雪里。

下山的路很长。他背着那个破旧的布包，里面是一柄断剑，半本残卷，还有娘亲临终前缝的护身符。走到山脚时，他回头望了一眼。云雾缭绕的山门在暮色里渐渐模糊，像一场做过的梦。

"沈砚。"身后忽然有人叫他。

他回头，看见大师兄林昭站在雪地里，手里拿着一件狐裘："山下冷，这个你带着。"

沈砚没有接。他笑了笑："师兄，替我照顾好师父。"

林昭沉默片刻，忽然压低声音："三日后，神武大比，北境的人会来。师父……他可能撑不过这次了。"

沈砚的脚步顿住了。

北境。那个名字像一根刺，扎在他心里十年。十年前，他的父亲就是死在北境铁骑的刀下，而青云门，选择了沉默。

"所以你要赶我走。"沈砚慢慢地说，"因为我要查那件事，会连累青云门。"

林昭没有回答，只是把狐裘塞进他怀里，转身走进了风雪里。

沈砚攥紧那件狐裘，忽然觉得，这世间最后一点暖意，也像这大雪一样，落在地上就化了。

他转身，走进了茫茫雪原。

没有人知道，十年后，这个名字会让整座大陆为之震颤。更没有人知道，他离开青云门的那天，其实带走了一个天大的秘密——残卷最后一页，藏着一门被天下人遗忘的功法。

剑起青山，自此，风起云涌。`,
  2: `三年后的秋天，沈砚在青州城的码头当了个苦力。

他卸了一整天的货，领到二十文铜钱，在街角的馄饨摊坐下。老板娘是个泼辣的妇人，看他衣衫褴褛，多给了两只馄饨。

"小兄弟，看你年纪轻轻，怎么干这个？"老板娘一边忙活一边问。

沈砚低头喝汤："讨生活。"

"讨生活？"老板娘嗤笑一声，"青州城讨生活的地方多了，赌坊、镖局、商行，哪个不比码头强？"

沈砚没接话。他不动声色地瞥了一眼街对面的茶楼——二楼雅间，坐着几个北境口音的人。这已经是他这个月第三次见到他们了。

"听说北境要跟咱们开战了。"邻桌的客人压着嗓子说，"北境铁骑都到雁门关了。"

"怕什么，朝廷不是派了镇北王？"

"镇北王？"那人冷笑，"十年前他就该打的仗，拖到现在。"

沈砚握着汤勺的手微微收紧。

十年前。又是十年前。

他放下碗，扔下铜钱，起身离开。走出几步，又折回来，对老板娘道："老板娘，你门口那盏灯笼，明晚别点。"

老板娘一愣："为什么？"

沈砚没有回答，消失在夜色里。

当夜三更，茶楼方向忽然火光冲天。第二日，青州城议论纷纷——据说昨夜有一伙北境细作在茶楼密会，被一场"意外"的大火烧得干干净净。

只有沈砚知道，那不是意外。他在残卷里学到的第一门功夫，叫"借火"。

而他在灰烬里，找到了一枚北境军的令牌。令牌背面，刻着一个名字。

那个名字，是他父亲的名字。`,
  3: `沿着那枚令牌的线索，沈砚用了半年时间，查到了雁门关。

他扮作行商混进关城时，正赶上入冬以来最大的一场雪。城门洞子里挤满了避雪的商队和难民，他缩在角落里，听人闲谈。

"听说了吗？镇北王要撤了。"

"撤？北境都快打到关下了，他撤什么？"

"朝廷说要议和。用雁门关外的三座城，换十年的太平。"

"十年太平？"有人啐了一口，"十年前也说要议和，结果呢？北境的人还不是打进来了。当年守关的沈将军——"

"嘘！"有人赶紧打断，"沈将军的名字，提不得。"

沈砚的心猛地一沉。沈将军。他的父亲，沈峥。

他正要上前，忽然一只手搭上了他的肩膀。他浑身一紧，反手扣住来人的手腕。

"别紧张。"那人笑道，"沈小将军，你爹的旧部，等你很久了。"

沈砚怔住。面前是个中年汉子，脸上有一道狰狞的疤，从眉心划到下颌。汉子见他发愣，压低声音："我叫赵铁，当年是你爹的亲卫。你爹出事那天，是我背着他回来的。"

"我爹……是怎么死的？"沈砚的声音有些发哑。

赵铁的表情变得复杂。他沉默了很久，才道："这里不是说话的地方。跟我来。"

雁门关内，一间不起眼的酒肆地窖里，赵铁摊开了一张泛黄的舆图。

"三座城，一条粮道，一个缺口。"赵铁指着舆图上的标记，"当年你爹发现，北境能一路打到雁门关，是因为朝中有人暗中放水。他写了一道密折，准备呈给先帝——然后，他就'战死'了。"

"密折呢？"

"在你师父手里。"赵铁苦笑，"你师父，当年是你爹的结义兄弟。青云门闭关三十年，你以为他为什么？他在替你爹守着那个秘密。"

沈砚想起三年前那个雪夜，想起师父那句"心性不坚"。原来如此。原来师父赶他走，是怕他死在青云门里。

"赵叔。"沈砚抬起头，眼睛亮得惊人，"北境铁骑，什么时候会再来？"

"最快，明年开春。"赵铁盯着他，"你想做什么？"

"我爹没做完的事。"沈砚一字一句，"我来做。"`,
  4: `这个冬天，雁门关外的雪下了整整三个月。

沈砚跟着赵铁，把父亲当年留下的暗桩一个个激活。他白天是商队里不起眼的伙计，夜里却沿着城墙的阴影，把一枚枚消息符送进各军帐。

腊月二十三，小年夜。赵铁带来了一个消息：朝中那位暗中放水的贵人，终于露了面。

"礼部侍郎崔元。"赵铁压低声音，"你爹的密折里写的就是他。这些年，他借着议和的名头，把边关的军费层层盘剥，转手卖给北境。"

"证据呢？"

"证据在崔府的书房里。'北境商路'的账册，他藏了十年。"

沈砚沉默片刻："崔府在京城。我进得去。"

"你疯了？"赵铁一把抓住他，"京城龙潭虎穴，你一个人——"

"我一个人，正好。"沈砚笑了笑，"人多了，反而引人注目。"

大年初三，沈砚离开了雁门关。他没有告诉赵铁，自己此去，其实还有另一件事——残卷的最后一页，那门被天下人遗忘的功法，需要一个特殊的东西才能修炼。而那个东西，就藏在崔府的地下密室里。

三千里路，他走了二十天。

元宵夜，京城灯火如昼。沈砚一身夜行衣，贴在崔府后花园的墙根下。他听着府里的丝竹声，忽然想起三年前那个雪夜，师父说："你心性不坚。"

他轻轻笑了一下。

心性不坚？或许吧。这世上，能让他停下脚步的东西，已经没有了。

他翻身上墙，身影融进月色。这一夜，崔府的书房少了一本账册，地下密室少了一枚玉匣。

而京城，多了一个夜行人。`,
  5: `账册和玉匣到手，沈砚却没有立刻离开京城。

崔元丢了东西，必然狗急跳墙。他需要一个时机，让证据见光的同时，自己全身而退。

元宵后的第三天，崔元果然上书弹劾镇北王"通敌叛国"，要求朝廷彻查边军。沈砚知道，这是崔元在倒打一耙，想先下手为强，把水搅浑。

当夜，他把账册的内容抄了三份：一份送去镇北王府，一份送去御史台，还有一份，送进了东宫。

第二日朝会，御史台当廷弹劾礼部侍郎崔元"贪墨军费、通敌卖国"，证据确凿。满朝哗然。

崔元在朝堂上当场瘫软，被禁军拖了下去。临出殿门时，他忽然嘶声喊道："沈峥！沈峥的密折在我手里！你们敢动我，我就把密折公之于众！"

满殿寂静。

皇帝的声音从龙椅上传来，听不出喜怒："崔元，你说沈峥的密折，在你手里？"

"是！"崔元像抓住了救命稻草，"那是先帝钦封的——"

"够了。"皇帝打断他，"沈峥的密折，早在十年前，就有人呈给先帝了。"

崔元的脸色瞬间惨白。

"你以为，青云门掌门三十年不出山，是为了什么？"皇帝慢慢说道，"他在替你爹，守着那道密折。等着你这样的蛀虫，自己跳出来。"

殿外的阳光斜斜照进来，沈砚站在人群里，忽然觉得眼睛有些发酸。

他转身，悄悄退出了朝堂。

三日后，朝廷昭告天下：追封镇北将军沈峥为忠武侯，其子沈砚，袭爵。

诏书送到沈砚住的客栈时，他已经离开了京城。桌上只留了一封信，一封给师父，一封给赵铁。

给师父的信上只有一句话："师父，雪停了。"

给赵铁的信上，则是一张舆图——北境商路的完整路线。

风从窗外吹进来，吹动桌上的信纸。驿卒追出门去，客栈门口空荡荡的，只有一匹白马，踏着晨光，向北而去。

雁门关外，雪已经化了。`,
}

interface MockChapter extends Chapter {
  novel_id: number
}

const chapters: MockChapter[] = Object.entries(chapterTexts).map(([no, content], i) => ({
  id: i + 1,
  novel_id: 1,
  chapter_no: Number(no),
  title: ['剑起青山', '青州夜火', '雁门旧事', '京城夜行', '雪化之时'][i],
  summary: '',
  content,
  word_count: countWords(content),
  status: 1,
  created_at: daysAgo(30 - i * 5),
  updated_at: daysAgo(0, 3 - i),
}))

// ---------------- 设定 / 大纲 / 记忆 ----------------
const novelSettings: Record<number, NovelSetting> = {
  1: {
    id: 1, novel_id: 1,
    world_view: '大陆分九州，青州在东，北境苦寒。修行者以灵气淬体，分为炼气、筑基、金丹、元婴、化神五境。青云门为青州第一宗门，却因三十年前一战元气大伤。',
    characters: '沈砚：主角，青云门弃徒，身负残卷功法，性格隐忍坚韧，重情重义。\n林昭：大师兄，暗中保护沈砚，立场摇摆。\n沈峥：沈砚之父，前镇北将军，十年前"战死"雁门关，实为朝中权贵所害。\n赵铁：沈峥旧部，忠义之士。\n崔元：礼部侍郎，通敌卖国的幕后黑手。',
    factions: '青云门（守旧派/主战派）、北境铁骑、朝堂（主和派崔元一系 / 主战派镇北王）、沈峥旧部暗桩。',
    conflicts: '主冲突：沈砚为父复仇，揭穿朝中通敌黑幕。\n副冲突：青云门在宗门存亡与正义之间的抉择；沈砚与林昭的兄弟情义在立场面前的考验。',
    main_plot: '沈砚被逐出青云门 → 发现父亲死因蹊跷 → 潜入北境查探 → 找到证据 → 京城翻案 → 袭爵北归，与北境铁骑决战。',
    style: '传统仙侠文风，雪、剑、月意象贯穿；节奏明快，爽点与虐点交替；对话简练有力，动作描写利落。',
  },
}

const outlines: Record<number, string> = {
  1: JSON.stringify({
    volumes: [
      {
        title: '第一卷 剑起青山',
        chapters: [
          { no: 1, title: '剑起青山', summary: '沈砚被逐出青云门，下山途中得知父亲死因蹊跷。' },
          { no: 2, title: '青州夜火', summary: '沈砚在青州做工，发现北境细作，借火焚之，得到父亲令牌。' },
          { no: 3, title: '雁门旧事', summary: '沈砚到雁门关，遇见父亲旧部赵铁，得知密折真相。' },
          { no: 4, title: '京城夜行', summary: '沈砚潜入京城崔府，盗取账册与玉匣。' },
          { no: 5, title: '雪化之时', summary: '朝堂翻案，崔元伏法，沈砚袭爵北归。' },
        ],
      },
      {
        title: '第二卷 北境风云',
        chapters: [
          { no: 6, title: '边关新雪', summary: '沈砚袭爵后赴雁门关，整顿边军，发现北境新动向。' },
          { no: 7, title: '暗流', summary: '军中混入奸细，沈砚设局引蛇出洞。' },
        ],
      },
    ],
  }),
}

/** 演示用 v2 结构化记忆（与后端记忆槽契约一致） */
const MOCK_STRUCTURED_MEMORY = {
  schema: 'v2',
  current_state: {
    location: '雁门关',
    time: '深秋，北境入冬前',
    plot_progress: '沈砚袭爵忠武侯，赶赴雁门关赴任，北境战事一触即发。',
  },
  characters: [
    { name: '沈砚', status: '忠武侯，雁门关主将', relationships: '赵铁为其父旧部；林昭立场未明', goals: '查明父亲当年之死，守住雁门关' },
    { name: '林昭', status: '京中世家子，随军幕僚', relationships: '与沈砚亦敌亦友', goals: '暗中调查家族与北境的联系' },
  ],
  foreshadowing: [
    { description: '玉匣中的功法尚未揭示', planted_chapter: 2, status: 'open', resolved_chapter: 0 },
    { description: '父亲当年之死的真相', planted_chapter: 1, status: 'open', resolved_chapter: 0 },
  ],
  world_facts: ['五境修行体系', '青云门与北境的百年恩怨'],
  timeline: [
    { chapter: 1, event: '沈砚袭爵忠武侯' },
    { chapter: 5, event: '北境狼烟再起，边军集结' },
  ],
  unresolved_events: ['林昭的立场选择', '北境大军的动向'],
  important_items: [
    { name: '玉匣', status: '尚未打开' },
    { name: '雁门关兵符', status: '已持有' },
  ],
  style_notes: '冷峻克制的文风，多用短句与环境描写',
}

const memories: Record<number, Memory> = {
  1: {
    id: 1, novel_id: 1,
    content: JSON.stringify(MOCK_STRUCTURED_MEMORY, null, 2),
    updated_at: daysAgo(1),
  },
}

const consistencyReportsStore: Record<number, ConsistencyReport[]> = {}

// ---------------- 点子 / 聊天 ----------------
const ideas: Idea[] = [
  { id: 1, category_id: 4, title: '循环的一天', content: '主角被困在同一天，每次死亡都会重来。直到第 99 次循环，他发现凶手也在循环，而且比他多一次。', status: 'unused', created_at: daysAgo(10), updated_at: daysAgo(10) },
  { id: 2, category_id: 1, title: '炼丹师的逆袭', content: '宗门炼丹师人人嘲笑他资质平庸，直到他炼出一炉改变大陆格局的神丹。', status: 'used', created_at: daysAgo(8), updated_at: daysAgo(2) },
  { id: 3, category_id: 2, title: '外卖员的系统', content: '外卖员获得系统：每送一单，就能预知一个陌生人的未来三分钟。', status: 'unused', created_at: daysAgo(5), updated_at: daysAgo(5) },
  { id: 4, category_id: 5, title: '替嫁王妃', content: '将军府庶女替嫡姐和亲，却在洞房夜发现新郎是自己的旧识。', status: 'unused', created_at: daysAgo(3), updated_at: daysAgo(3) },
]

const ideaMessages: Record<number, IdeaMessage[]> = {
  1: [
    { id: 1, idea_id: 1, role: 'user', content: '我想写一个循环题材的悬疑故事，帮我展开一下设定。', created_at: daysAgo(10) },
    { id: 2, idea_id: 1, role: 'assistant', content: '好的！循环题材的关键在于"增量"——每一次循环都要让读者看到新信息。\n\n**核心设定建议**：\n1. 主角的循环起点是"便利店的第 99 次日出"；\n2. 主角会在第 99 次循环中死去，却意外发现凶手也在循环；\n3. 凶手比他多一次循环，意味着凶手知道"正确结局"。\n\n**建议的悬念钩子**：主角在最后一次循环里，发现自己在"第一次循环"留下的日记。', created_at: daysAgo(10) },
  ],
}

// ---------------- AI 配置 ----------------
const providers: AiProvider[] = [
  { id: 1, name: 'DeepSeek', base_url: 'https://api.deepseek.com/v1', api_key: 'sk-****3f2a', status: 1, is_default: 1, created_at: daysAgo(60) },
  { id: 2, name: 'OpenAI', base_url: 'https://api.openai.com/v1', api_key: 'sk-****9c1d', status: 1, is_default: 0, created_at: daysAgo(40) },
  { id: 3, name: 'Ollama 本地', base_url: 'http://127.0.0.1:11434/v1', api_key: 'ollama', status: 0, is_default: 0, created_at: daysAgo(30) },
]

const models: AiModel[] = [
  { id: 1, provider_id: 1, name: 'deepseek-chat', display_name: 'DeepSeek V3', max_tokens: 8192, temperature: 0.8, status: 1, is_default: 1 },
  { id: 2, provider_id: 1, name: 'deepseek-reasoner', display_name: 'DeepSeek R1', max_tokens: 8192, temperature: 0.7, status: 1, is_default: 0 },
  { id: 3, provider_id: 2, name: 'gpt-4o-mini', display_name: 'GPT-4o mini', max_tokens: 16384, temperature: 0.8, status: 1, is_default: 0 },
  { id: 4, provider_id: 3, name: 'qwen2.5:7b', display_name: 'Qwen 2.5 7B', max_tokens: 4096, temperature: 0.7, status: 0, is_default: 0 },
]

// ---------------- Prompt ----------------
const prompts: Prompt[] = [
  { id: 1, type: 'idea_chat', name: '点子聊天助手', description: '与用户围绕小说点子进行头脑风暴、设定展开', content: '你是一位资深小说编辑，擅长帮助作者展开灵感。请围绕用户的想法进行深入探讨：提出关键问题、给出设定建议、指出潜在矛盾。语气亲切专业，回答使用中文。', updated_at: daysAgo(30) },
  { id: 2, type: 'novel_setting', name: '生成小说设定', description: '根据小说信息生成世界观/人物/势力/冲突/主线/文风', content: '请根据以下小说信息生成完整设定，覆盖：世界背景、主要人物（含性格与动机）、势力阵营、核心冲突、主线剧情梗概、文风建议。\n\n小说标题：{{title}}\n简介：{{description}}\n分类：{{category}}\n标签：{{tags}}', updated_at: daysAgo(30) },
  { id: 3, type: 'outline', name: '生成大纲', description: '根据设定生成分卷分章大纲', content: '请根据小说设定生成大纲，输出 JSON 格式：{"volumes":[{"title":"第X卷 ...","chapters":[{"no":1,"title":"章节名","summary":"本章概要"}]}]}。要求：至少 2 卷，每卷 3-8 章，每章概要 30-80 字，情节有递进、有高潮。\n\n小说标题：{{title}}\n设定：{{setting}}', updated_at: daysAgo(30) },
  { id: 4, type: 'chapter_generate', name: '生成章节', description: '根据大纲与记忆生成章节正文', content: '请根据以下信息创作小说章节正文，字数约 {{target_words}} 字：\n\n小说标题：{{title}}\n章节标题：{{chapter_title}}\n本章概要：{{summary}}\n前情记忆：{{memory}}\n\n要求：文笔流畅，符合小说文风，结尾留悬念。', updated_at: daysAgo(30) },
  { id: 5, type: 'chapter_summary', name: '生成章节摘要', description: '为章节生成一句话摘要', content: '请为以下章节生成一句话摘要（不超过 40 字），用于大纲与目录展示：\n\n章节标题：{{chapter_title}}\n正文：{{content}}', updated_at: daysAgo(30) },
  { id: 6, type: 'memory_update', name: '更新记忆', description: '根据最新章节更新小说长期记忆', content: '请根据最新章节内容，更新小说记忆库。输出 JSON 数组：[{"key":"记忆条目名","value":"记忆内容"}]。需覆盖：主线进度、新增人物、伏笔、设定变化。\n\n小说标题：{{title}}\n现有记忆：{{memory}}\n最新章节：{{content}}', updated_at: daysAgo(30) },
  { id: 7, type: 'chapter_continue', name: '续写章节', description: '在章节末尾继续创作', content: '请从以下位置继续创作小说章节，保持文风一致，字数约 {{target_words}} 字，不要重复已有内容：\n\n小说标题：{{title}}\n章节标题：{{chapter_title}}\n已有正文末尾：{{tail}}\n记忆：{{memory}}', updated_at: daysAgo(30) },
]

// ---------------- 任务 / 日志 ----------------
const tasks: AiTask[] = [
  { id: 1, task_type: 'generate_outline', ref_id: 1, ref_type: 'novel', status: 'success', error_message: '', created_at: daysAgo(30, 2), updated_at: daysAgo(30, 1) },
  { id: 2, task_type: 'generate_setting', ref_id: 1, ref_type: 'novel', status: 'success', error_message: '', created_at: daysAgo(29, 5), updated_at: daysAgo(29, 4) },
  { id: 3, task_type: 'generate_chapter', ref_id: 1, ref_type: 'novel', status: 'success', error_message: '', created_at: daysAgo(0, 4), updated_at: daysAgo(0, 3) },
  { id: 4, task_type: 'continue_chapter', ref_id: 1, ref_type: 'novel', status: 'failed', error_message: '上游模型超时，请重试', created_at: daysAgo(0, 1), updated_at: daysAgo(0, 1) },
]

const logs: AiLog[] = [
  { id: 1, provider: 'DeepSeek', model: 'deepseek-chat', task_type: 'generate_chapter', prompt_tokens: 3200, completion_tokens: 4100, total_tokens: 7300, duration: 82340, status: 1, error_message: '', created_at: daysAgo(0, 3) },
  { id: 2, provider: 'DeepSeek', model: 'deepseek-chat', task_type: 'continue_chapter', prompt_tokens: 2800, completion_tokens: 3560, total_tokens: 6360, duration: 65420, status: 0, error_message: '上游模型超时，请重试', created_at: daysAgo(0, 1) },
  { id: 3, provider: 'OpenAI', model: 'gpt-4o-mini', task_type: 'generate_summary', prompt_tokens: 900, completion_tokens: 120, total_tokens: 1020, duration: 5210, status: 1, error_message: '', created_at: daysAgo(1, 6) },
  { id: 4, provider: 'DeepSeek', model: 'deepseek-reasoner', task_type: 'generate_outline', prompt_tokens: 1800, completion_tokens: 2400, total_tokens: 4200, duration: 124800, status: 1, error_message: '', created_at: daysAgo(2) },
]

const config: SystemConfig = {
  site_name: '拾光小说',
  ai_temperature: 0.8,
  ai_http_timeout: 120,
  context_max_recent_chapters: 3,
  context_summary_max_chars: 300,
  chapter_target_words: 3000,
}

function dashboard(): Dashboard {
  return {
    novel_count: novels.length,
    chapter_count: chapters.length,
    total_words: novels.reduce((s, n) => s + n.word_count, 0),
    idea_count: ideas.length,
    running_tasks: tasks.filter((t) => t.status === 'running' || t.status === 'pending').length,
    today_chapters: chapters.filter((c) => c.updated_at.startsWith(new Date().toISOString().slice(0, 10))).length,
    recent_tasks: [...tasks].sort((a, b) => b.id - a.id).slice(0, 6),
    recent_logs: [...logs].sort((a, b) => b.id - a.id).slice(0, 8),
  }
}

// ---------------- Mock 流式响应 ----------------
/** 模拟流式输出：把文本按小步增量推给回调，支持中断 */
export function mockStreamText(
  text: string,
  handlers: StreamHandlers,
  signal?: AbortSignal,
  step = 2,
  interval = 24,
): Promise<void> {
  return new Promise((resolve) => {
    let i = 0
    const timer = setInterval(() => {
      if (signal?.aborted) {
        clearInterval(timer)
        resolve()
        return
      }
      const end = Math.min(i + step, text.length)
      handlers.onChunk?.(text.slice(i, end))
      handlers.onDelta?.(text.slice(i, end))
      i = end
      if (i >= text.length) {
        clearInterval(timer)
        handlers.onDone?.()
        resolve()
      }
    }, interval)
  })
}

// ============================================================
// Mock 实现（返回 data 部分，与真实接口一致）
// ============================================================
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms))
const paged = <T>(all: T[], page = 1, pageSize = 10): PageResult<T> => ({
  list: all.slice((page - 1) * pageSize, page * pageSize),
  total: all.length,
  page,
  page_size: pageSize,
})

export const mockApi = {
  // ---- 前台 ----
  async home(): Promise<HomeData> {
    await sleep(200)
    const recent = [...novels]
      .filter((n) => n.status === 'published' || n.status === 'finished')
      .sort((a, b) => b.updated_at.localeCompare(a.updated_at))
      .slice(0, 8)
      .map(withCategoryName)
    return {
      recent_updates: recent,
      categories: categories
        .filter((c) => c.status === 1)
        .map((c) => ({
          id: c.id,
          name: c.name,
          novel_count: novels.filter((n) => n.category_id === c.id && n.is_public === 1).length,
        })),
    }
  },

  async categories(): Promise<{ list: { id: number; name: string; novel_count: number }[] }> {
    await sleep(120)
    return {
      list: categories
        .filter((c) => c.status === 1)
        .map((c) => ({ id: c.id, name: c.name, novel_count: novels.filter((n) => n.category_id === c.id).length })),
    }
  },

  async novelList(params: { category_id?: number | string; keyword?: string; page?: number; page_size?: number }): Promise<PageResult<Novel>> {
    await sleep(250)
    let list = novels.filter((n) => n.is_public === 1 || n.status !== 'draft')
    if (params.category_id && Number(params.category_id) !== 0) {
      list = list.filter((n) => n.category_id === Number(params.category_id))
    }
    if (params.keyword) {
      const kw = params.keyword.trim().toLowerCase()
      list = list.filter((n) => n.title.toLowerCase().includes(kw) || n.tags.toLowerCase().includes(kw))
    }
    const page = params.page ?? 1
    const pageSize = params.page_size ?? 12
    return { ...paged(list.map(withCategoryName), page, pageSize) }
  },

  async novelDetail(id: number): Promise<NovelDetail> {
    await sleep(200)
    const n = novels.find((x) => x.id === Number(id))
    if (!n) throw new Error('小说不存在')
    const first = chapters.filter((c) => c.novel_id === n.id).sort((a, b) => a.chapter_no - b.chapter_no)[0]
    return { ...withCategoryName(n), first_no: first?.chapter_no ?? 0 }
  },

  async novelChapters(id: number): Promise<{ list: ChapterListItem[]; total: number }> {
    await sleep(200)
    const list = chapters
      .filter((c) => c.novel_id === Number(id))
      .sort((a, b) => a.chapter_no - b.chapter_no)
      .map((c) => ({ chapter_no: c.chapter_no, title: c.title, word_count: c.word_count, updated_at: c.updated_at }))
    return { list, total: list.length }
  },

  async chapterRead(id: number, no: number): Promise<ChapterRead> {
    await sleep(200)
    const list = chapters.filter((c) => c.novel_id === Number(id)).sort((a, b) => a.chapter_no - b.chapter_no)
    const idx = list.findIndex((c) => c.chapter_no === Number(no))
    if (idx < 0) throw new Error('章节不存在')
    const c = list[idx]
    return {
      chapter: c,
      prev_no: idx > 0 ? list[idx - 1].chapter_no : null,
      next_no: idx < list.length - 1 ? list[idx + 1].chapter_no : null,
    }
  },

  // ---- 认证 ----
  async login(username: string, password: string): Promise<LoginResult> {
    await sleep(400)
    if (username === MOCK_USERNAME && password === MOCK_PASSWORD) return { username }
    throw new Error('用户名或密码错误')
  },
  async me(): Promise<LoginResult> {
    return { username: MOCK_USERNAME }
  },

  // ---- 仪表盘 ----
  async dashboard(): Promise<Dashboard> {
    await sleep(200)
    return dashboard()
  },

  // ---- 分类 ----
  async categoryList(): Promise<PageResult<Category>> {
    await sleep(150)
    const list = [...categories].sort((a, b) => a.sort - b.sort).map((c) => ({
      ...c,
      novel_count: novels.filter((n) => n.category_id === c.id).length,
    }))
    return { list, total: list.length, page: 1, page_size: 100 }
  },
  async createCategory(body: Partial<Category>): Promise<Category> {
    await sleep(200)
    const c: Category = {
      id: nid(), name: body.name ?? '', description: body.description ?? '', sort: body.sort ?? 0,
      status: body.status ?? 1, created_at: now(), updated_at: now(),
    }
    categories.push(c)
    return c
  },
  async updateCategory(id: number, body: Partial<Category>): Promise<Category> {
    await sleep(200)
    const c = categories.find((x) => x.id === Number(id))
    if (!c) throw new Error('分类不存在')
    Object.assign(c, body, { updated_at: now() })
    return c
  },
  async deleteCategory(id: number): Promise<void> {
    await sleep(200)
    const idx = categories.findIndex((x) => x.id === Number(id))
    if (idx >= 0) categories.splice(idx, 1)
  },

  // ---- 小说 ----
  async adminNovelList(params: { page?: number; page_size?: number; keyword?: string; category_id?: number | string; status?: string }): Promise<PageResult<Novel>> {
    await sleep(250)
    let list = [...novels]
    if (params.keyword) {
      const kw = params.keyword.trim().toLowerCase()
      list = list.filter((n) => n.title.toLowerCase().includes(kw) || n.tags.toLowerCase().includes(kw))
    }
    if (params.category_id && Number(params.category_id) !== 0) {
      list = list.filter((n) => n.category_id === Number(params.category_id))
    }
    if (params.status) list = list.filter((n) => n.status === params.status)
    const page = params.page ?? 1
    const pageSize = params.page_size ?? 10
    return { ...paged(list.map(withCategoryName), page, pageSize) }
  },
  async adminNovelDetail(id: number): Promise<NovelDetail> {
    const n = novels.find((x) => x.id === Number(id))
    if (!n) throw new Error('小说不存在')
    return { ...withCategoryName(n), first_no: chapters.filter((c) => c.novel_id === n.id).length ? 1 : 0 }
  },
  async createNovel(body: Partial<Novel>): Promise<Novel> {
    await sleep(250)
    const n: Novel = {
      id: nid(), category_id: body.category_id ?? 0, title: body.title ?? '未命名小说',
      cover: body.cover ?? '', description: body.description ?? '', tags: body.tags ?? '',
      status: body.status ?? 'draft', is_public: body.is_public ?? 0,
      word_count: 0, chapter_count: 0, created_at: now(), updated_at: now(),
    }
    novels.unshift(n)
    return withCategoryName(n)
  },
  async updateNovel(id: number, body: Partial<Novel>): Promise<Novel> {
    await sleep(250)
    const n = novels.find((x) => x.id === Number(id))
    if (!n) throw new Error('小说不存在')
    Object.assign(n, body, { updated_at: now() })
    n.word_count = chapters.filter((c) => c.novel_id === n.id).reduce((s, c) => s + c.word_count, 0)
    n.chapter_count = chapters.filter((c) => c.novel_id === n.id).length
    return withCategoryName(n)
  },
  async deleteNovel(id: number): Promise<void> {
    await sleep(250)
    const idx = novels.findIndex((x) => x.id === Number(id))
    if (idx >= 0) novels.splice(idx, 1)
  },

  // ---- 章节 ----
  async adminChapters(id: number): Promise<Chapter[]> {
    await sleep(200)
    return chapters.filter((c) => c.novel_id === Number(id)).sort((a, b) => a.chapter_no - b.chapter_no)
  },
  async createChapter(id: number, body: { title: string; content: string; summary?: string }): Promise<Chapter> {
    await sleep(250)
    const list = chapters.filter((c) => c.novel_id === Number(id))
    const maxNo = list.reduce((m, c) => Math.max(m, c.chapter_no), 0)
    const ch: MockChapter = {
      id: nid(), novel_id: Number(id), chapter_no: maxNo + 1, title: body.title,
      summary: body.summary ?? '', content: body.content, word_count: countWords(body.content),
      status: 1, created_at: now(), updated_at: now(),
    }
    chapters.push(ch)
    this.updateNovel(id, {})
    return ch
  },
  async updateChapter(id: number, body: Partial<Chapter>): Promise<Chapter> {
    await sleep(250)
    const c = chapters.find((x) => x.id === Number(id))
    if (!c) throw new Error('章节不存在')
    Object.assign(c, body, { updated_at: now() })
    if (body.content !== undefined) c.word_count = countWords(c.content)
    this.updateNovel(c.novel_id, {})
    return c
  },
  async deleteChapter(id: number): Promise<void> {
    await sleep(250)
    const idx = chapters.findIndex((x) => x.id === Number(id))
    if (idx >= 0) chapters.splice(idx, 1)
  },

  // ---- 设定 / 大纲 / 记忆 ----
  async getSetting(id: number): Promise<NovelSetting> {
    await sleep(150)
    return novelSettings[Number(id)] ?? { id: 0, novel_id: Number(id), world_view: '', characters: '', factions: '', conflicts: '', main_plot: '', style: '' }
  },
  async saveSetting(id: number, body: Partial<NovelSetting>): Promise<NovelSetting> {
    await sleep(200)
    const cur = novelSettings[Number(id)] ?? { id: nid(), novel_id: Number(id), world_view: '', characters: '', factions: '', conflicts: '', main_plot: '', style: '' }
    Object.assign(cur, body)
    novelSettings[Number(id)] = cur
    return cur
  },
  async getOutline(id: number): Promise<{ outline: string }> {
    await sleep(150)
    return { outline: outlines[Number(id)] ?? '' }
  },
  async saveOutline(id: number, outline: string): Promise<void> {
    await sleep(200)
    outlines[Number(id)] = outline
  },
  async getMemory(id: number): Promise<Memory> {
    await sleep(150)
    return memories[Number(id)] ?? {
      id: 0,
      novel_id: Number(id),
      content: JSON.stringify(MOCK_STRUCTURED_MEMORY),
      updated_at: now(),
    }
  },
  async saveMemory(id: number, content: string): Promise<Memory> {
    await sleep(200)
    const cur = memories[Number(id)] ?? { id: nid(), novel_id: Number(id), content: '{}', updated_at: now() }
    cur.content = content
    cur.updated_at = now()
    memories[Number(id)] = cur
    return cur
  },

  // ---- 一致性审校 ----
  async consistencyReports(id: number): Promise<ConsistencyReport[]> {
    await sleep(200)
    return consistencyReportsStore[Number(id)] ?? []
  },
  async runConsistency(id: number): Promise<AiTask> {
    const t = await mockApi.createTask({ task_type: 'consistency_check', novel_id: id })
    setTimeout(() => {
      const arr = consistencyReportsStore[Number(id)] ?? []
      arr.unshift({
        id: nid(), novel_id: Number(id), chapter_no: 12, status: 'warning', created_at: now(),
        report: {
          status: 'warning',
          summary: '整体推进基本符合大纲，但存在两处需要关注的伏笔与时间线问题。',
          issues: [
            {
              severity: 'major', type: 'foreshadowing_dropped',
              description: '第3章埋下的"古戒残魂"伏笔在后续章节中未被提及，原设定的回收节点可能已被错过。',
              suggestion: '在后续章节安排一次古戒异动或梦境，重新激活该伏笔。',
              related_chapters: [3],
            },
            {
              severity: 'minor', type: 'timeline_conflict',
              description: '第6章提到"入门两月"，与第5章的"入门三月"表述不一致。',
              suggestion: '统一时间表述，建议以"入门三月"为准。',
              related_chapters: [5, 6],
            },
          ],
        },
      })
      consistencyReportsStore[Number(id)] = arr
    }, 6500)
    return t
  },

  // ---- 点子 ----
  async ideaList(params: { page?: number; page_size?: number; category_id?: number | string; status?: string }): Promise<PageResult<Idea>> {
    await sleep(250)
    let list = [...ideas]
    if (params.category_id && Number(params.category_id) !== 0) {
      list = list.filter((i) => i.category_id === Number(params.category_id))
    }
    if (params.status) list = list.filter((i) => i.status === params.status)
    const page = params.page ?? 1
    const pageSize = params.page_size ?? 10
    return {
      ...paged(list.map((i) => ({ ...i, category_name: categories.find((c) => c.id === i.category_id)?.name ?? '未分类' })), page, pageSize),
    }
  },
  async createIdea(body: Partial<Idea>): Promise<Idea> {
    await sleep(250)
    const i: Idea = {
      id: nid(), category_id: body.category_id ?? 0, title: body.title ?? '未命名点子',
      content: body.content ?? '', status: 'unused', created_at: now(), updated_at: now(),
    }
    ideas.unshift(i)
    ideaMessages[i.id] = []
    return i
  },
  async updateIdea(id: number, body: Partial<Idea>): Promise<Idea> {
    await sleep(250)
    const i = ideas.find((x) => x.id === Number(id))
    if (!i) throw new Error('点子不存在')
    Object.assign(i, body, { updated_at: now() })
    return i
  },
  async deleteIdea(id: number): Promise<void> {
    await sleep(250)
    const idx = ideas.findIndex((x) => x.id === Number(id))
    if (idx >= 0) ideas.splice(idx, 1)
  },
  async ideaMessages(id: number): Promise<IdeaMessage[]> {
    await sleep(200)
    return ideaMessages[Number(id)] ?? []
  },
  async saveIdea(id: number, body: { title?: string; content?: string }): Promise<Idea> {
    await sleep(250)
    const i = ideas.find((x) => x.id === Number(id))
    if (!i) throw new Error('点子不存在')
    if (body.title) i.title = body.title
    if (body.content) i.content = body.content
    i.updated_at = now()
    return i
  },
  async createNovelFromIdea(id: number): Promise<{ novel_id: number }> {
    await sleep(300)
    const i = ideas.find((x) => x.id === Number(id))
    const n = await this.createNovel({
      category_id: i?.category_id, title: i?.title ?? '来自点子的新小说',
      description: i?.content?.slice(0, 200) ?? '',
    })
    if (i) i.status = 'used'
    return { novel_id: n.id }
  },

  // ---- AI 配置 ----
  async providerList(): Promise<AiProvider[]> {
    await sleep(200)
    return [...providers]
  },
  async createProvider(body: Partial<AiProvider>): Promise<AiProvider> {
    await sleep(250)
    const p: AiProvider = { id: nid(), name: body.name ?? '', base_url: body.base_url ?? '', api_key: body.api_key ? `sk-****${body.api_key.slice(-4)}` : '', status: body.status ?? 1, is_default: 0, created_at: now() }
    providers.push(p)
    return p
  },
  async updateProvider(id: number, body: Partial<AiProvider>): Promise<AiProvider> {
    await sleep(250)
    const p = providers.find((x) => x.id === Number(id))
    if (!p) throw new Error('Provider 不存在')
    Object.assign(p, body)
    if (body.api_key) p.api_key = `sk-****${body.api_key.slice(-4)}`
    return p
  },
  async deleteProvider(id: number): Promise<void> {
    await sleep(250)
    const idx = providers.findIndex((x) => x.id === Number(id))
    if (idx >= 0) providers.splice(idx, 1)
  },
  async setProviderDefault(id: number): Promise<void> {
    await sleep(200)
    providers.forEach((p) => (p.is_default = p.id === Number(id) ? 1 : 0))
  },
  async modelList(): Promise<AiModel[]> {
    await sleep(200)
    return models.map((m) => ({ ...m, provider_name: providers.find((p) => p.id === m.provider_id)?.name ?? '未知' }))
  },
  async createModel(body: Partial<AiModel>): Promise<AiModel> {
    await sleep(250)
    const m: AiModel = { id: nid(), provider_id: body.provider_id ?? 0, name: body.name ?? '', display_name: body.display_name ?? '', max_tokens: body.max_tokens ?? 4096, temperature: body.temperature ?? 0.8, status: body.status ?? 1, is_default: 0 }
    models.push(m)
    return m
  },
  async updateModel(id: number, body: Partial<AiModel>): Promise<AiModel> {
    await sleep(250)
    const m = models.find((x) => x.id === Number(id))
    if (!m) throw new Error('模型不存在')
    Object.assign(m, body)
    return m
  },
  async deleteModel(id: number): Promise<void> {
    await sleep(250)
    const idx = models.findIndex((x) => x.id === Number(id))
    if (idx >= 0) models.splice(idx, 1)
  },
  async setModelDefault(id: number): Promise<void> {
    await sleep(200)
    models.forEach((m) => (m.is_default = m.id === Number(id) ? 1 : 0))
  },

  // ---- Prompt ----
  async promptList(): Promise<Prompt[]> {
    await sleep(150)
    return [...prompts]
  },
  async updatePrompt(id: number, content: string): Promise<Prompt> {
    await sleep(200)
    const p = prompts.find((x) => x.id === Number(id))
    if (!p) throw new Error('Prompt 不存在')
    p.content = content
    p.updated_at = now()
    return p
  },

  // ---- 任务 ----
  async taskList(params: { novel_id?: number | string; page?: number; page_size?: number }): Promise<PageResult<AiTask>> {
    await sleep(200)
    let list = [...tasks].sort((a, b) => b.id - a.id)
    if (params.novel_id) list = list.filter((t) => t.ref_id === Number(params.novel_id))
    const page = params.page ?? 1
    const pageSize = params.page_size ?? 10
    return { ...paged(list, page, pageSize) }
  },
  async createTask(body: { task_type: TaskType; novel_id?: number; params?: Record<string, unknown> }): Promise<AiTask> {
    await sleep(300)
    const t: AiTask = {
      id: nid(), task_type: body.task_type, ref_id: body.novel_id ?? 1, ref_type: 'novel',
      status: 'running', error_message: '', created_at: now(), updated_at: now(),
    }
    tasks.unshift(t)
    // 模拟运行完成后置为 success
    setTimeout(() => {
      t.status = 'success'
      t.updated_at = now()
    }, 6000)
    return t
  },
  async taskDetail(id: number): Promise<AiTask> {
    await sleep(100)
    const t = tasks.find((x) => x.id === Number(id))
    if (!t) throw new Error('任务不存在')
    return t
  },
  async retryTask(id: number): Promise<AiTask> {
    await sleep(200)
    const t = tasks.find((x) => x.id === Number(id))
    if (!t) throw new Error('任务不存在')
    t.status = 'running'
    t.error_message = ''
    t.updated_at = now()
    setTimeout(() => {
      t.status = 'success'
      t.updated_at = now()
    }, 4000)
    return t
  },

  // ---- 日志 ----
  async logList(params: { page?: number; page_size?: number }): Promise<PageResult<AiLog>> {
    await sleep(200)
    const page = params.page ?? 1
    const pageSize = params.page_size ?? 15
    return { ...paged([...logs].sort((a, b) => b.id - a.id), page, pageSize) }
  },
  async clearLogs(): Promise<void> {
    await sleep(200)
    logs.splice(0, logs.length)
  },

  // ---- 配置 ----
  async getConfig(): Promise<SystemConfig> {
    await sleep(150)
    return { ...config }
  },
  async saveConfig(body: SystemConfig): Promise<SystemConfig> {
    await sleep(200)
    Object.assign(config, body)
    return { ...config }
  },
}

export { novelsById }
