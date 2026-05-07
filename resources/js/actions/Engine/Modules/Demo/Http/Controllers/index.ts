import DemoController from './DemoController'
import LabController from './LabController'
import LayoutDemoController from './LayoutDemoController'
const Controllers = {
    DemoController: Object.assign(DemoController, DemoController),
LabController: Object.assign(LabController, LabController),
LayoutDemoController: Object.assign(LayoutDemoController, LayoutDemoController),
}

export default Controllers